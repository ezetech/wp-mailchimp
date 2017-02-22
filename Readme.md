# MailChimp&WordPress Integration

This plugin made for Eze.tech projects.

## Install

Copy `ezetech-mailchimp` folder to plugin folder or use `composer install ezetech/wp-mailchimp` command.

## Mailchimp set up

- Get your MailChimp [API key](https://admin.mailchimp.com/account/api/).
- Make the template with Custom HTML Template. More [info](http://kb.mailchimp.com/templates/code/how-to-import-a-custom-html-template)
- You should have `mc:edit='body'` in the template. More [info](http://kb.mailchimp.com/templates/code/create-editable-content-areas-with-mailchimps-template-language)

## Usage

Add to your `function.php` in active theme:
```php
$api_key = 'your_mailchimp_api_key'; //required
$template_id = 123456; //required
$taxonomy = 'mailchimp_custom_taxonomy_name'; //default mailchimp_list
$email_post_type = 'email_post_type'; //default 'post'
add_theme_support('ezetech-mailchimp', $api_key, $template_id, $email_post_type, $taxonomy);
```