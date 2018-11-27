<?php

ini_set('max_execution_time', 60);
ini_set('display_startup_errors', 1);
ini_set('display_errors', 1);
error_reporting(-1);

if (!class_exists('Astra_updater')) {

    class Astra_updater {

        protected $paths = array();
        protected $server_version;
        protected $update_file_name;
        protected $updated = FALSE;

        public function __construct($server_version = "") {
            $this->server_version = $server_version;
            $this->paths['local_path'] = dirname(dirname(__FILE__)) . '/updates/';
            echo_debug("Local path: " . $this->paths['local_path']);
        }

        public function is_update() {
            if (1==1 || version_compare($this->server_version, CZ_ASTRA_CLIENT_VERSION) > 0) {
                $this->update_file_name = 'astra-' . trim($this->server_version) . '.zip';
                echo_debug("Needs to be updated: " . $this->update_file_name);
                return TRUE;
            } else {
                echo_debug("Update not required as:");
                echo_debug(array("Server Version" => $this->server_version, "Client Version" => CZ_ASTRA_CLIENT_VERSION));
                return FALSE;
            }
        }

        protected function is_valid_zip() {

            $zip = new ZipArchive;

            $res = $zip->open($this->paths['local_path'] . $this->update_file_name, ZipArchive::CHECKCONS);

            if ($res !== TRUE) {
                switch ($res) {
                    case ZipArchive::ER_NOZIP :
                        echo_debug("Not a ZIP");
                        $ret = FALSE;
                    /* Not a zip archive */
                    case ZipArchive::ER_INCONS :
                        echo_debug("Consistency check failed");
                        $ret = FALSE;
                    /* die('consistency check failed'); */
                    case ZipArchive::ER_CRC :
                        echo_debug("Error with CRC");
                        $ret = FALSE;
                    default :
                        echo_debug("Checksum Failed");
                        $ret = FALSE;
                    /* die('checksum failed'); */
                }

                if ($ret) {
                    $zip->close();
                }
                return $ret;
            } else {
                echo_debug("Update file is a valid ZIP");
                return TRUE;
            }
        }

        public function download_file() {

            if (!is_file($this->paths['local_path'] . $this->update_file_name)) {

                /* Download the File */
                require_once(ASTRAPATH . 'libraries/Crypto.php');

                $dataArray['client_key'] = CZ_CLIENT_KEY;
                $dataArray['api'] = "download_package";

                $str = serialize($dataArray);
                $crypto = new Astra_crypto();
                $encrypted_data = $crypto->encrypt($str, CZ_SECRET_KEY);

                $postdata = http_build_query(
                        array(
                            'encRequest' => $encrypted_data,
                            'access_code' => CZ_ACCESS_KEY,
                        )
                );

                $opts = array('http' =>
                    array(
                        'method' => 'POST',
                        'header' => 'Content-type: application/x-www-form-urlencoded',
                        'content' => $postdata
                    )
                );
                $context = stream_context_create($opts);

                /**/
                $newUpdate = file_get_contents(CZ_API_URL, FALSE, $context);

                if (!is_dir($this->paths['local_path'])) {
                    echo_debug("Making updates directory");
                    mkdir($this->paths['local_path']);
                }

                if (is_writable(dirname($this->paths['local_path'] . $this->update_file_name))) {
                    $dlHandler = fopen($this->paths['local_path'] . $this->update_file_name, 'w');
                    if (!fwrite($dlHandler, $newUpdate)) {
                        return FALSE;
                        exit();
                    }
                    fclose($dlHandler);

                    if ($this->is_valid_zip()) {
                        return TRUE;
                    } else {
                        /*
                          unlink($this->paths['local_path'] . $this->update_file_name);
                          echo_debug("Deleted ZIP file since it was not a valid.");
                         *
                         */
                        return FALSE;
                    }
                }
            } else {
                echo_debug("Unable to write the file since the file probably exists");
                return TRUE;
            }
        }

        protected function migration() {
            if (file_exists(ASTRAPATH . 'upgrade.php')) {
                echo_debug("Upgrade file exists and will be executed");
                $output = shell_exec('php ' . ASTRAPATH . 'upgrade.php');
                echo_debug($output);
                unlink(ASTRAPATH . 'upgrade.php');
                echo_debug("Upgrade file deleted");
                return TRUE;
            }

            if (!strpos(__FILE__, 'xampp')) {
                
            } else {
                echo_debug("We are in local");
                $fp = dirname(dirname(dirname(__FILE__))) . '/astra/' . 'upgrade.php';
                echo_debug($fp);
                if (file_exists($fp)) {
                    echo_debug("Upgrade file exists and will be executed");
                    $output = shell_exec('php ' . $fp);
                    echo_debug($output);
                    unlink($fp);
                    echo_debug("Upgrade file deleted");
                    return TRUE;
                }
            }

            echo_debug('Upgrade file does not exist');
            return TRUE;
        }

        public function update($platform = 'php') {
            $zip = new ZipArchive;
            if ($zip->open($this->paths['local_path'] . $this->update_file_name) === TRUE) {
                if ($platform == 'php') {
                    $extract_to = dirname(ASTRAPATH);
                } else {
                    $extract_to = dirname(dirname(ASTRAPATH));
                }
                echo_debug("Will extract Update to: " . $extract_to);
                $extracted = $zip->extractTo($extract_to);
                $zip->close();
                $this->migration();

                if ($extracted) {
                    echo_debug("ZIP successfully extracted");
                    return TRUE;
                } else {
                    echo_debug("ZIP extraction not successful");
                    return FALSE;
                }
            }
            echo_debug("Unable to open Update ZIP File");
            return FALSE;
        }

        public function delete() {
            if (file_exists($this->paths['local_path'] . $this->update_file_name)) {
                if (unlink($this->paths['local_path'] . $this->update_file_name)) {
                    echo_debug("Just deleted: " . $this->paths['local_path'] . $this->update_file_name);
                    return TRUE;
                } else {
                    echo_debug("File Exists but unable to delete: " . $this->paths['local_path'] . $this->update_file_name);
                    return FALSE;
                }
            } else {
                echo_debug("Unable to delete: " . $this->paths['local_path'] . $this->update_file_name);
            }
        }

    }

}