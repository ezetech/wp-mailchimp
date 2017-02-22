<?php
/**
 * Plugin Name: Ezetech MailChimp Integration
 * Plugin URI: https://github.com/ezetech/wp-mailchimp
 * Description: WordPress Plugin for MailChimp Integration.
 * Author: Ezetech
 * Version: 0.1
 * Author URI: http://eze.tech/
 */

require_once('MailChimp.php');
require_once('Batch.php');
use DrewM\MailChimp\MailChimp;

class EzetechMailChimp
{
    const META_FIELD_NAME = 'mailchimp-meta';

    private $mailChimpApi;

    private $taxonomy;

    private $postType;

    private $actionName;

    private $subject_line;

    private $reply_to;

    private $template_id;

    public function __construct($apiKey, $template_id, $postType = 'post', $taxonomy = 'mailchimp-list')
    {
        $this->template_id  = $template_id;
        $this->mailChimpApi = new MailChimp($apiKey);
        $this->postType     = $postType;
        $this->taxonomy     = $taxonomy;

        $this->actionName = 'sync_' . $this->taxonomy;

        // TODO: make it more flexiable
        $this->subject_line = 'News from ' . get_bloginfo('name');
        $this->from_name    = get_bloginfo('name');
        $this->reply_to     = get_bloginfo('admin_email');

        $this->init();
    }

    public function init()
    {
        add_action('init', [$this, 'addTaxonomy']);
        add_action('after-' . $this->taxonomy . '-table', [$this, 'showSyncButton']);
        add_action('wp_ajax_' . $this->actionName, [$this, 'sync']);
        add_action('publish_' . $this->postType, [$this, 'newPublishHandler'], 10, 2);
    }

    public function addTaxonomy()
    {
        $labels = [
            'name'          => 'Mailchimp Lists',
            'singular_name' => 'List',
        ];
        $args   = [
            'labels'            => $labels,
            'hierarchical'      => true,
            'public'            => false,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => false,
            'show_tagcloud'     => false,
            'rewrite'           => false,
            'show_in_rest'      => false,
        ];
        register_taxonomy($this->taxonomy, $this->postType, $args);
    }

    public function showSyncButton($taxonomy)
    {
        $url = admin_url('admin-ajax.php') . '?action=' . $this->actionName;
        echo "<form action='" . $url . "' method='post'>";
        echo "<input type='hidden' name='taxonomy' value='" . $taxonomy . "'>";
        echo "<input type='submit' class='right button button-primary' value='Update list from MailChimp'></form>";
    }

    public function sync()
    {
        $result = $this->mailChimpApi->get('lists');
        $this->checkMailChimpApiError();

        foreach ($result['lists'] as $list) {
            $term = term_exists($list['name'], $this->taxonomy);
            if ($term) {
                wp_update_term($term['term_id'], $this->taxonomy, []);
            } else {
                $term = wp_insert_term($list['name'], $this->taxonomy, []);
            }
            update_term_meta($term['term_id'], self::META_FIELD_NAME, serialize($list));
        }

        wp_safe_redirect(admin_url('edit-tags.php?taxonomy=' . $this->taxonomy));
    }

    private function checkMailChimpApiError()
    {
        if (!$this->mailChimpApi->success()) {
            $errorMessage = "MailChimp API error: " . $this->mailChimpApi->getLastError();
            error_log($errorMessage);
            wp_die($errorMessage);
        }
    }

    public function newPublishHandler($ID, $post)
    {
        $listIds = array_map(function ($list) {
            $serialized = get_term_meta($list->term_id, self::META_FIELD_NAME, true);

            return unserialize($serialized)['id'];
        }, wp_get_post_terms($ID, $this->taxonomy));

        if (!count($listIds)) {
            return;
        }

        foreach ($listIds as $listId) {
            $campaignId = $this->createCampaign($listId);
            $this->setCampaignContent($campaignId, $post->post_content);
            $this->sendCampaign($campaignId);
        }
    }

    protected function createCampaign($listId)
    {
        $result = $this->mailChimpApi->post('campaigns',
            [
                'type'       => 'regular',
                'recipients' => [
                    'list_id' => $listId
                ],
                'settings'   => [
                    'subject_line' => $this->subject_line,
                    'from_name'    => $this->from_name,
                    'reply_to'     => $this->reply_to
                ]
            ]);
        $this->checkMailChimpApiError();

        return $result['id'];
    }

    protected function setCampaignContent($campaignId, $content)
    {
        $this->mailChimpApi->put("campaigns/$campaignId/content",
            [
                'template' => [
                    'id'       => $this->template_id,
                    'sections' =>
                        [
                            'body' => $content
                        ]
                ],

            ]);
        $this->checkMailChimpApiError();
    }

    protected function sendCampaign($campaignId)
    {
        $this->mailChimpApi->post("campaigns/$campaignId/actions/send");
        $this->checkMailChimpApiError();
    }
}

add_action('after_setup_theme', function () {
    if (current_theme_supports('ezetech-mailchimp')) {
        $params = get_theme_support('ezetech-mailchimp');
        new EzetechMailChimp(...$params);
    }
});
