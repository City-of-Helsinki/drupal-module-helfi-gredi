# Drupal integration with Gredi API

Integrates [Gredi API ](https://helsinki.contenthub.fi/) with Drupal.

## Dependencies

- PHP 8.0 or higher
- views_remote_data:^1.0
- drupal/media_library

## Installation

1. Clone from git repository [https://github.com/City-of-Helsinki/drupal-module-helfi-gredi].
2. Install via drush command `drush en -y helfi_gredi`.
3. Configure credentials at `/admin/config/gredi-dam` specifying the base API url, username,
password, customer path (e.g `6`) and the upload folder id from the API. (e.g `16293292`).
4. Create or edit a media reference field to accept the media type `Gredi Image`.


## Usage

The module allows you to integrate a Drupal website with the Gredi API.
The module provides two main features:
1. Integration with the core module media_library to display a widget for retrieving images from the API.
2. Upload feature that allows sending images to the API. The feature is based on the media library upload
file functionality with the mention that the images are also sent to the API.
The module creates a view that is used by media_library to display the newly created media type Gredi Image.

## Cron Jobs

A cron job is implemented in order to keep the stored data in sync with the API.
The frequency of the sync is set for 24h.

## Known Issues

- Slow external API server responses that are not regarded with the implementation of the module.

## Support

Slack: #helfi-drupal (http://helsinkicity.slack.com/)

## Testing

The code was tested with PHPUnit. Version PHPUnit 9.5.26


