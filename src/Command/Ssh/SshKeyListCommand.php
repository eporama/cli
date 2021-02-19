<?php

namespace Acquia\Cli\Command\Ssh;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use violuke\RsaSshKeyFingerprint\FingerprintGenerator;

/**
 * Class SshKeyListCommand.
 */
class SshKeyListCommand extends SshKeyCommandBase {

  protected static $defaultName = 'ssh-key:list';

  /**
   * {inheritdoc}.
   */
  protected function configure() {
    $this->setDescription('List your local and remote SSH keys');
  }

  /**
   * @param \Symfony\Component\Console\Input\InputInterface $input
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *
   * @return int 0 if everything went fine, or an exit code
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $acquia_cloud_client = $this->cloudApiClientService->getClient();
    $cloud_keys = $acquia_cloud_client->request('get', '/account/ssh-keys');
    $local_keys = $this->findLocalSshKeys();

    $table = new Table($output);
    $table->setHeaders([
      'Cloud platform key label and UUID',
      'Local key filename',
      'Hashes — sha256 and md5',
    ]);
    // First list all keys for which there are both Cloud and local keys.
    $last_key = array_key_last($cloud_keys);
    foreach ($local_keys as $local_index => $local_file) {
      foreach ($cloud_keys as $index => $cloud_key) {
        if (trim($local_file->getContents()) === trim($cloud_key->public_key)) {
          $sha256_hash = FingerprintGenerator::getFingerprint($cloud_key->public_key, 'sha256');
          $table->addRow([
            $cloud_key->label . PHP_EOL . $cloud_key->uuid,
            $local_file->getFilename(),
            $sha256_hash . PHP_EOL . $cloud_key->fingerprint,
          ]);
          if ($last_key !== $index) {
            $table->addRow(new TableSeparator());
          }
          unset($cloud_keys[$index], $local_keys[$local_index]);
          break;
        }
      }
    }
    // Second list all cloud keys for which there is no local key.
    $last_key = array_key_last($cloud_keys);
    foreach ($cloud_keys as $index => $cloud_key) {
      $sha256_hash = FingerprintGenerator::getFingerprint($cloud_key->public_key, 'sha256');
      $table->addRow([
        $cloud_key->label . PHP_EOL . $cloud_key->uuid,
        'none',
        $sha256_hash . PHP_EOL . $cloud_key->fingerprint,
      ]);
      if ($last_key !== $index || $local_keys) {
        $table->addRow(new TableSeparator());
      }
    }

    // Last list all local keys for which there is no cloud key.
    $last_key = array_key_last($local_keys);
    foreach ($local_keys as $index => $local_file) {
      $sha256_hash = FingerprintGenerator::getFingerprint($local_file->getContents(), 'sha256');
      $md5_hash = FingerprintGenerator::getFingerprint($local_file->getContents(), 'md5');
      $table->addRow([
        'none',
        $local_file->getFilename(),
        $sha256_hash . PHP_EOL . $md5_hash,
      ]);
      if ($last_key !== $index) {
        $table->addRow(new TableSeparator());
      }
    }
    $table->render();

    return 0;
  }

}
