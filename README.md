# Drupal integration with Gredi API

![CI](https://github.com/City-of-Helsinki/drupal-module-helfi-gredi/workflows/CI/badge.svg)
Integrates [Gredi API ](https://helsinki.contenthub.fi/) with Drupal.

## Dependencies

- PHP 8.0 or higher
- views_remote_data:^1.0
- drupal/media_library

## Installation

1. Install with composer and enable the module.
2. Configure credentials at `/admin/config/gredi-dam` specifying the base API url, username,
password, customer path and the upload folder id from the API.
3. Configure the form display of the media type `Gredi Image` as you want.
4. Create or edit a media reference field to accept the media type `Gredi Image`.


## Usage

The module allows you to integrate a Drupal website with the Gredi API.
The module provides two main features:
1. Integration with the core module media_library to display a widget for retrieving images from the API.
2. Upload feature that allows sending images to the API. The feature is based on the media library upload
file functionality with the mention that the images are also sent to the API.

## Cron Jobs

A cron job is implemented in order to keep the stored data in sync with the API.
The frequency of the sync is set for 24h.

## Known Issues

- Slow external API server responses that are not regarded with the implementation of the module.

## Contact

Slack: #helfi_ibm | #platta (http://helsinkicity.slack.com/)


