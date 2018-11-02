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


    /**
     * Return true if there is an update available
     * @return bool
     */
    public  function availableUpdate(){
        return $this->availableUpdate;
    }

    /**
     * Returns current version of phpList.
     *
     * @return string
     */
    public function getCurrentVersion()
    {
        $jsonVersion = file_get_contents('version.json');
        $decodedVersion = json_decode($jsonVersion, true);
        $currentVersion = isset($decodedVersion['version']) ? $decodedVersion['version'] : '';

        return $currentVersion;
    }

    /**
     * Checks if there is an Update Available
     * @return string
     * @throws \Exception
     */
    function checkIfThereIsAnUpdate()
    {
        $serverResponse = $this->getResponseFromServer();
        $version = isset($serverResponse['version']) ? $serverResponse['version'] : '';

        $versionString = isset($serverResponse['versionstring']) ? $serverResponse['versionstring'] : '';
        if ($version!== '' && $version !== $this->getCurrentVersion() && version_compare($this->getCurrentVersion(), $version)) {
            $this->availableUpdate = true;
            $updateMessage = 'Update to the ' . htmlentities($versionString) . ' is available.  ' ;
        } else {
            $updateMessage = 'phpList is up-to-date.';
        }
        if ($this->availableUpdate && isset($serverResponse['autoupdater']) && !($serverResponse['autoupdater'] === 1 || $serverResponse['autoupdater'] === '1')) {
            $this->availableUpdate = false;
            $updateMessage .= '<br />The one click updater is disabled for this update.';
        }

        return $updateMessage;

    }

    /**
     *
     * Return version data from server
     * @return array
     * @throws \Exception
     */
    function getResponseFromServer()
    {
        $serverUrl = "http://10.211.55.7/version.json";

        // create a new cURL resource
        $ch = curl_init();
        // set URL and other appropriate options
        // Disable SSL verification
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        // Will return the response, if false it print the response
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // Set the url
        curl_setopt($ch, CURLOPT_URL, $serverUrl);
        // Execute
        $responseFromServer = curl_exec($ch);
        // Closing
        curl_close($ch);

        // decode json
        $responseFromServer = json_decode($responseFromServer, true);

        return $responseFromServer;
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
     * Recursively delete a directory and all of it's contents - e.g.the equivalent of `rm -r` on the command-line.
     * Consistent with `rmdir()` and `unlink()`, an E_WARNING level error will be generated on failure.
     *
     * @param string $dir absolute path to directory to delete
     *
     * @return bool true on success; false on failure
     */

    private function rmdir_recursive($dir)
    {

        if (false === file_exists($dir)) {
            return false;
        }

        /** @var SplFileInfo[] $files */
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $fileinfo) {
            if ($fileinfo->isDir()) {
                if (false === rmdir($fileinfo->getRealPath())) {
                    return false;
                }
            } else {
                if (false === unlink($fileinfo->getRealPath())) {
                    return false;
                }
            }
        }
        return rmdir($dir);
    }

    /**
     * Delete old files except config and other files that we want to keep
     * @TODO check if we need to exclude init.php or not
     */

    function deleteFiles() {

        $excludedFiles = array(
            'config.php',
            'config_extended.php',
            'init.php',
        );





    }

    /**
     * Get a PDO connection
     * @return PDO
     */
    function getConnection(){

        require __DIR__.'/../../config/config.php';

        $charset = 'utf8mb4';

        /** @var string $database_host
         *  @var string $database_name
         * @var string $database_user
         * @var string $database_password
         */

        $dsn = "mysql:host=$database_host;dbname=$database_name;charset=$charset";
        $options = array(
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        );
        try {
            $pdo = new PDO($dsn, $database_user, $database_password, $options);
        } catch (\PDOException $e) {
            throw new \PDOException($e->getMessage(), (int)$e->getCode());
        }
        return $pdo;
    }

    /**
     *Set the maintenance mode
     * @return bool true - maintenance mode is set; false - maintenance mode could not be set because an update is already running
     */
    function addMaintenanceMode()
    {
        $prepStmt = $this->getConnection()->prepare("SELECT * FROM phplist_config WHERE item=?");
        $prepStmt->execute(array('update_in_progress'));
        $result = $prepStmt->fetch(PDO::FETCH_ASSOC);
        if($result === false ){
            // the row does not exist => no update running
            $this->getConnection()
                ->prepare('INSERT INTO phplist_config(`item`,`editable`,`value`) VALUES (?,0,?)')
                ->execute(array('update_in_progress','1'));
        }elseif ($result['update_in_progress'] == '0'){
            $this->getConnection()
                ->prepare('UPDATE phplist_config SET `value`=? WHERE `item`=?')
                ->execute(array('1','update_in_progress'));
        }else{
            // the row exists and is not 0 => there is an update running
            return false;
        }
        $name = 'maintenancemode';
        $value = "Update process ";
        $sql = "UPDATE phplist_config SET value =?, editable =? where item =? ";
        $this->getConnection()->prepare($sql)->execute(array($value, 0, $name));

    }

    /**
     *Clear the maintenance mode and remove the update_in_progress lock
     */
    function removeMaintenanceMode()
    {
        $name = 'maintenancemode';
        $value = '';
        $sql = "UPDATE phplist_config SET value =?, editable =? where item =? ";
        $this->getConnection()->prepare($sql)->execute(array($value, 0, $name));

        $this->getConnection()
            ->prepare('UPDATE phplist_config SET `value`=? WHERE `item`=?')
            ->execute(array("0","update_in_progress"));

    }



    /**
     * Download and unzip phpList from remote server
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

    function cleanUp()
    {
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }
    }
}

$update = new updater();
//if(!$update->addMaintenanceMode()){
    //TODO define how you want to progress if there is already an update running.
//    die('There is already an update running');
//}
var_dump($update->checkWritePermissions());
var_dump($update->checkRequiredFiles());
var_dump($update->getCurrentVersion());
var_dump($update->getResponseFromServer());
var_dump($update->checkIfThereIsAnUpdate());
//$update->downloadUpdate();
//$update->removeMaintenanceMode();


/**
 *
 * @ToDo write start and end action functions
 *
 */
if(isset($_POST['action'])) {
    set_time_limit(0);

    //ensure that $action is integer

    $action = (int)$_POST['action'];

    switch ($action) {
        case 1:
            $unexpectedFiles = $update->checkRequiredFiles();
            if(count($unexpectedFiles) !== 0) {
                echo(json_encode(array('continue' => false, 'response' => $unexpectedFiles)));
            } else {
                echo(json_encode(array('continue' => true)));
            }
            break;
        case 2:
            $notWriteableFiles = $update->checkWritePermissions();
            if(count($notWriteableFiles) !== 0) {
                echo(json_encode(array('continue' => false, 'response' => $notWriteableFiles)));
            } else {
                echo(json_encode(array('continue' => true)));
            }
            break;
        case 3:
            try {
                $update->downloadUpdate();
                echo(json_encode(array('continue' => true)));
            } catch (\Exception $e) {
                echo(json_encode(array('continue' => false, 'response' => $e->getMessage())));
            }
            break;

    };



}
?>

<html>
<head>
    <link rel="stylesheet" href="style.css">
    <script src="update.js"></script>
</head>
<body>
<div class="progress-bar">
    <div id="bar" class="progress-value" style="width: 1%;">
        <span id="value">1%</span>
        <span id="action"> <?php echo $action; ?></span>
    </div>
</div>



</body>
</html>
