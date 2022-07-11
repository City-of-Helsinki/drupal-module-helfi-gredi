<?php

namespace Drupal\helfi_gredi_image\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;

/**
 * A worker that updates metadata for every image.
 *
 * @QueueWorker(
 *   id = "meta_update",
 *   title = @Translation("Meta Update")
 * )
 */
class MetaUpdate extends QueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $fields = [
      'to' => $data->email,
      'h:Reply-To' => self::MAILGUN_REPLYTO,
      'subject' => $data->subject,
      'text' => $data->text_content,
      'html' => $data->html_content,
    ];

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, self::MAILGUN_URL);
    curl_setopt($ch, CURLOPT_USERPWD, 'api:' . self::MAILGUN_KEY);
    curl_setopt($ch, CURLOPT_POST, TRUE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

    curl_exec($ch);
    if (curl_errno($ch)) {
      echo 'Error:' . curl_error($ch);
      C24Logger::DEBUG(curl_error($ch), static::class);
    }
    curl_close($ch);

    \Drupal::logger('GrediMetaData')->notice('Metadata for Gredi image with id  ' . $data->id);
  }

}
