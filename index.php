<?php
/**
 * One click updater for phpList 3
 * @author Xheni Myrtaj <xheni@phplist.com>
 *
 */

class UpdateException extends \Exception {}

class updater
{
    /** @var bool */
    private $availableUpdate = false;

    const DOWNLOAD_PATH = '../tmp_uploaded_update';

    private $excludedFiles = array(
        'dl.php',
        'index.php',
        'index.html',
        'lt.php',
        'ut.php',
    );

    /**
     * Return true if there is an update available
     * @return bool
     */
    public function availableUpdate()
    {
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
        if ($version !== '' && $version !== $this->getCurrentVersion() && version_compare($this->getCurrentVersion(), $version)) {
            $this->availableUpdate = true;
            $updateMessage = 'Update to the ' . htmlentities($versionString) . ' is available.  ';
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


    /**
     * Checks write permissions and returns files that are not writable
     * @return array
     */
    function checkWritePermissions()
    {

        $directory = new \RecursiveDirectoryIterator(__DIR__ . '/../', \RecursiveDirectoryIterator::SKIP_DOTS); // Exclude dot files
        /** @var SplFileInfo[] $iterator */
        $iterator = new \RecursiveIteratorIterator($directory, RecursiveIteratorIterator::CHILD_FIRST);
        $files = array();
        foreach ($iterator as $info) {
            if (!is_writable($info->getRealPath())) {
                $files[] = $info->getRealPath();
            }
        }

        return $files;

    }

    /**
     * @return array
     */
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

        $existingFiles = scandir(__DIR__ . '/../');

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
     *
     * Recursively delete a directory and all of it's contents - e.g.the equivalent of `rm -r` on the command-line.
     * Consistent with `rmdir()` and `unlink()`, an E_WARNING level error will be generated on failure.
     *
     * @param string $dir absolute path to directory to delete
     *
     * @return bool true on success; false on failure
     * @throws UpdateException
     */

    private function rmdir_recursive($dir)
    {

        if (false === file_exists($dir)) {
            throw new \UpdateException("$dir doesn't exist.");
        }

        /** @var SplFileInfo[] $files */
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $fileinfo) {
            if ($fileinfo->isDir()) {
                if (false === rmdir($fileinfo->getRealPath())) {
                    throw new \UpdateException("Could not delete $fileinfo");
                }
            } else {
                if (false === unlink($fileinfo->getRealPath())) {
                    throw new \UpdateException("Could not delete $fileinfo");
                }
            }
        }
        return rmdir($dir);
    }

    /**
     * Delete dirs/files except config and other files that we want to keep
     * @throws UpdateException
     */

    function deleteFiles()
    {

        $excludedFolders = array(
            'config',
            'tmp_uploaded_update',
            'updater',
            '.',
            '..',
        );

        $filesTodelete = scandir(__DIR__ . '/../');

        foreach ($filesTodelete as $fileName) {
            $absolutePath = __DIR__ . '/../' . $fileName;
            $is_dir = false;
            if (is_dir($absolutePath)) {
                $is_dir = true;
                if (in_array($fileName, $excludedFolders)) {
                    echo "$fileName is excluded<br/>";
                    continue;
                }

            } else if (is_file($absolutePath)) {
                if (in_array($fileName, $this->excludedFiles)) {
                    echo "$fileName is excluded<br/>";
                    continue;
                }

            }


            if ($is_dir) {
                $this->rmdir_recursive($absolutePath);
            } else {
                unlink($absolutePath);
            }
        }

    }

    /**
     * Get a PDO connection
     * @return PDO
     */
    function getConnection()
    {

        require __DIR__ . '/../../config/config.php';

        $charset = 'utf8mb4';

        /** @var string $database_host
         * @var string $database_name
         * @var string $database_user
         * @var string $database_password
         */

        $dsn = "mysql:host=$database_host;dbname=$database_name;charset=$charset";
        $options = array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
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
        if ($result === false) {
            // the row does not exist => no update running
            $this->getConnection()
                ->prepare('INSERT INTO phplist_config(`item`,`editable`,`value`) VALUES (?,0,?)')
                ->execute(array('update_in_progress', '1'));
        } elseif ($result['update_in_progress'] == '0') {
            $this->getConnection()
                ->prepare('UPDATE phplist_config SET `value`=? WHERE `item`=?')
                ->execute(array('1', 'update_in_progress'));
        } else {
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
            ->execute(array("0", "update_in_progress"));

    }


    /**
     * Download and unzip phpList from remote server
     *
     * @throws UpdateException
     */
    function downloadUpdate()
    {
        /** @var string $url */
        $url = "http://10.211.55.7/phplist.zip";
        $zipFile = tempnam(sys_get_temp_dir(), 'phplist-update');
        if ($zipFile === false) {
            throw new UpdateException("Temporary file cannot be created");
        }
        // Get The Zip File From Server
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FAILONERROR, true);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_FILE, fopen($zipFile, 'w+'));
        $page = curl_exec($ch);
        if (!$page) {
            echo "Error :- " . curl_error($ch);
        }
        curl_close($ch);

        // extract files
        $this->unZipFiles($zipFile, self::DOWNLOAD_PATH);

    }

    /**
     * Creates temporary dir
     * @throws UpdateException
     */
    function temp_dir()
    {

        $tempdir = mkdir(self::DOWNLOAD_PATH, 0700);
        if ($tempdir === false) {
            throw new UpdateException("Could not create temporary file");
        }
    }


    function cleanUp()
    {
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }
    }

    /**
     * @throws UpdateException
     */
    function replacePHPEntryPoints()
    {
        $entryPoints = array(
            'dl.php',
            'index.html',
            'lt.php',
            'ut.php',
        );
        foreach ($entryPoints as $key => $fileName) {
            $entryFile = fopen($fileName, "w");
            if ($entryFile === FALSE) {
                throw new UpdateException("Could not fopen $fileName");
            }
            $current = "Update in progress \n";
            $content = file_put_contents($entryFile, $current);
            if ($content === FALSE) {
                throw new UpdateException("Could not write to the $fileName");
            }
        }

    }

    /**
     * Returns true if the file/dir is excluded otherwise false.
     * @param $file
     * @return bool
     */
    function isExcluded($file)
    {

        $excludedFolders = array(
            'config',
            'tmp_uploaded_update',
            'updater',
            '.',
            '..',
        );


        if (in_array($file, $excludedFolders)) {
            return true;
        } else if (in_array($file, $this->excludedFiles)) {
            return true;
        }
        return false;
    }

    /**
     * @throws UpdateException
     */
    function moveNewFiles()
    {
        $rootDir = __DIR__ . '/../tmp_uploaded_update/phplist/public_html/lists';
        $downloadedFiles = scandir($rootDir);
        if (count($downloadedFiles) <= 2) {
            throw new UpdateException("Download folder is empty!");
        }

        foreach ($downloadedFiles as $fileName) {
            if ($this->isExcluded($fileName)) {
                continue;
            }
            $oldFile = $rootDir . '/' . $fileName;
            $newFile = __DIR__ . '/../' . $fileName;
            $state = rename($oldFile, $newFile);
            if ($state === false) {
                throw new UpdateException("Could not move new files");
            }
        }
    }

    function moveEntryPHPpoints()
    {
        $rootDir = __DIR__ . '/../tmp_uploaded_update/phplist/public_html/lists';
        $downloadedFiles = scandir($rootDir);

        foreach ($downloadedFiles as $filename) {
            $oldFile = $rootDir . '/' . $filename;
            $newFile = __DIR__ . '/../' . $filename;
            if (in_array($filename, $this->excludedFiles)) {
                rename($oldFile, $newFile);
            }
        }

    }


    /**
     * backUpFiles('/path/to/folder', '/path/to/backup.zip';
     * @param $source /path to folder
     * @param $destination 'path' to backup zip
     * @return bool
     */
    function backUpFiles($source, $destination)
    {
        if (extension_loaded('zip') === true) {
            if (file_exists($source) === true) {
                $zip = new ZipArchive();
                if ($zip->open($destination, ZIPARCHIVE::CREATE) === true) {
                    $source = realpath($source);
                    if (is_dir($source) === true) {
                        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source), RecursiveIteratorIterator::SELF_FIRST);
                        foreach ($files as $file) {
                            $file = realpath($file);
                            if (is_dir($file) === true) {
                                $zip->addEmptyDir(str_replace($source . '/', '', $file . '/'));
                            } else if (is_file($file) === true) {
                                $zip->addFromString(str_replace($source . '/', '', $file), file_get_contents($file));
                            }
                        }
                    } else if (is_file($source) === true) {
                        $zip->addFromString(basename($source), file_get_contents($source));
                    }
                }
                return $zip->close();
            }
        }
        return false;
    }

    /**
     * Extract Zip Files
     * @param string $toBeExtracted
     * @param string $extractPath
     * @throws UpdateException
     */
    function unZipFiles($toBeExtracted, $extractPath)
    {
        $zip = new ZipArchive;

        /* Open the Zip file */
        if ($zip->open($toBeExtracted) !== true) {
            throw new \UpdateException("Unable to open the Zip File");
        }
        /* Extract Zip File */
        $zip->extractTo($extractPath);
        $zip->close();

    }

    /**
     * @throws UpdateException
     */
    function recoverFiles()
    {
        $this->unZipFiles('backup.zip', self::DOWNLOAD_PATH);
    }

    /**
     * @param $status
     * @param $action
     * @throws UpdateException
     */
    function writeActions($status,$action)
    {
        $actionsdir = __DIR__ . '/../config/actions.txt';
        if (!file_exists($actionsdir)) {
            $actionsFile = fopen($actionsdir, "w+");
            if ($actionsFile === false) {
                throw new \UpdateException("Could not create actions file!");
            }
        }
        $writen = file_put_contents($actionsdir, json_encode(array('continue'=>$status, 'step'=>$action)));
        if($writen===false){
            throw new \UpdateException("Could not write on $actionsdir");
        }

    }

    /**
     * Return the current step
     * @return mixed array of json data
     * @throws UpdateException
     */
    function currentUpdateStep(){
        $actionsdir = __DIR__ . '/../config/actions.txt';
        if (file_exists($actionsdir)) {
            $status= file_get_contents($actionsdir);
            if($status===false){
                throw new \UpdateException( "Cannot read content from $actionsdir");
            }
            $decodedJson = json_decode($status,true);
            if (!is_array($decodedJson)) {
                throw new \UpdateException('JSON data cannot be decoded!');
            }

        } else {
            throw new \UpdateException($actionsdir.' does not exist!');
        }
        return $decodedJson;

    }
}

try {
    $update = new updater();
    $update->writeActions(true,1);
    $update->writeActions(true,2);
    $update->writeActions(false,3);
    //  $update->temp_dir();
    // $update->deleteFiles();
    // $update->downloadUpdate();
    //$update->moveNewFiles();
    //$update->moveEntryPHPpoints();
//if(!$update->addMaintenanceMode()){
//    die('There is already an update running');
//}
//var_dump($update->checkWritePermissions());
//var_dump($update->checkRequiredFiles());
//var_dump($update->getCurrentVersion());
//var_dump($update->getResponseFromServer());
//var_dump($update->checkIfThereIsAnUpdate());
//$update->downloadUpdate();
//$update->removeMaintenanceMode();
//$update->backUpFiles('../../../', '../backup.zip');

} catch (\UpdateException $e) {
    throw $e;
}


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
        case 0:
            $statusJson= $update->currentUpdateStep();
            echo json_encode($statusJson);
            break;
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



}else{
    ?>

    <html>
    <head>

    </head>
    <body>
    <script>
        let req = new XMLHttpRequest();
        let url = "http://10.211.55.4/phplist-3.3.6/public_html/lists/updater/index.php";
        req.open('POST', url, true);
        req.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
        req.onload  = function() {
            console.log (JSON.parse(req.responseText));
            // do something with jsonResponse
        };
        req.send("action=0");

    </script>

    Current step: <span id="current-step"> </span> <br>


    <button id="next-step"> Next</button>


    </body>
    </html>
<?php } ?>