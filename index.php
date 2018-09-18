<?php
/**
 * One click updater for phpList 3
 * @author Xheni Myrtaj <xheni@phplist.com>
 *
 */

class updater
{
    /** @var bool */
    private $availableUpdate = false;
    /** @var string */
    /** @var string */
    private $url;


    /**
     * Returns current version of phpList.
     *
     * @return string
     */
    public function getCurrentVersion()
    {
        $jsonVersion = file_get_contents('/../version.json/');
        $decodedVersion = json_decode($jsonVersion, true);
        $currentVersion = isset($decodedVersion['version']) ? $decodedVersion['version'] : '';

        return $currentVersion;
    }

    /**
     * @return string
     * @throws \Exception
     */
    function checkIfThereIsAnUpdate()
    {
        $serverResponse = $this->checkResponseFromServer();
        $version = isset($serverResponse['version']) ? $serverResponse['version'] : '';
        $versionString = isset($serverResponse['versionstring']) ? $serverResponse['versionstring'] : '';
        if ($version !== '' && $version !== $this->getCurrentVersion()) {
            $this->availableUpdate = true;
            $updateMessage = 'Update to the' . htmlentities($versionString) . ' is available. <br />The following phpList file will be downloaded: <code ">' . $serverResponse['url'] . '</code>';
        } else {
            $updateMessage = 'There is no update available.';
        }
        if ($this->availableUpdate && isset($serverResponse['autoupdater']) && !($serverResponse['autoupdater'] === 1 || $serverResponse['autoupdater'] === '1')) {
            $this->availableUpdate = false;
            $updateMessage .= '<br />The one click updater is disabled for this update.';
        }

        return $updateMessage;

    }

    /**
     *
     *
     * @return array
     * @throws \Exception
     */
    private function checkResponseFromServer()
    {

        $serverUrl = "http://10.211.55.7/version.json";

        // create a new cURL resource
        $ch = curl_init();
        // set URL and other appropriate options
        curl_setopt($ch, CURLOPT_URL, $serverUrl);
        curl_setopt($ch, CURLOPT_HEADER, 0);

        // grab URL
        $responseFromServer = curl_exec($ch);

        if ($responseFromServer === false) {
            throw new \Exception(curl_error($ch));
        }
        // close cURL resource, and free up system resources

        curl_close($ch);
        //Encode the response to json
        $jsonEncoded = json_encode($responseFromServer);

        //Decode the response
        $jsonDecoded = json_decode($jsonEncoded, true);

        return $jsonDecoded;
    }


    function checkWritePermissions()
    {

        $directory = new \RecursiveDirectoryIterator(__DIR__ . '/../../');
        /** @var SplFileInfo[] $iterator */
        $iterator = new \RecursiveIteratorIterator($directory);
        $files = array();
        foreach ($iterator as $info) {
            if (!is_writable($info->getRealPath())) {
                $files[] = $info->getRealPath();
            }
        }
        return $files;

    }

    function checkRequiredFiles()
    {
        $expectedFiles = array(
            '.' => 1,
            '..' => 1,
            'admin' => 1,
            'config' => 1,
            'images' => 1,
            'js' => 1,
            'styles' => 1,
            'texts' => 1,
            '.htaccess' => 1,
            'dl.php' => 1,
            'index.html' => 1,
            'index.php' => 1,
            'lt.php' => 1,
            'ut.php' => 1,
        );

        $existingFiles = scandir(__DIR__ . '/../../');

        foreach ($existingFiles as $fileName) {

            if (isset($expectedFiles[$fileName])) {
                unset($expectedFiles[$fileName]);
            } else {
                $expectedFiles[$fileName] = 1;
            }

        }
        return $expectedFiles;


    }

    /**
     * @return string
     */
    function checkUserPermission()
    {
    }

    /**
     *
     */
    function downloadUpdate()
    {
        echo exec('whoami');
        /** @var string $url */
        $url = "http://10.211.55.7/phplist.zip";
        /** @var ZipArchive $zipFile */
        $zipFile = "phplist.zip"; // Local Zip File Path
        $zipResource = fopen($zipFile, "w");
        // Get The Zip File From Server
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FAILONERROR, true);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER,true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_FILE, $zipResource);
        $page = curl_exec($ch);
        if(!$page) {
            echo "Error :- ".curl_error($ch);
        }
        curl_close($ch);
        /* Open the Zip file */
        $zip = new ZipArchive;
        $extractPath = getcwd(); // extract to the current path
        if($zip->open($zipFile) != "true"){
            echo "Error :- Unable to open the Zip File";
        }
        /* Extract Zip File */
        $zip->extractTo($extractPath);
        $zip->close();
        die(', phpList was probably extracted, please check.');

    }
}

$update = new updater();
var_dump($update->checkWritePermissions());
var_dump($update->checkRequiredFiles());
var_dump($update->checkUserPermission());
$update->downloadUpdate();

