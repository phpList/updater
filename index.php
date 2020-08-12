<?php
/**
 * Automatic updater for phpList 3
 *
 * @author Xheni Myrtaj <xheni@phplist.com>
 */

class UpdateException extends \Exception
{
}

class updater
{
    /** @var bool */
    private $availableUpdate = false;
    const DOWNLOAD_PATH = '../tmp_uploaded_update';
    const ELIGIBLE_SESSION_KEY = 'phplist_updater_eligible';
    private $excludedFiles = array(
        'dl.php',
        'index.php',
        'index.html',
        'lt.php',
        'ut.php',
        'api.php',
    );

    public function isAuthenticated()
    {

        ini_set('session.name','phpListSession');
        ini_set('session.cookie_samesite','Strict');
        ini_set('session.use_only_cookies',1);
        ini_set('session.cookie_httponly',1);        session_start();
        if (isset($_SESSION[self::ELIGIBLE_SESSION_KEY]) && $_SESSION[self::ELIGIBLE_SESSION_KEY] === true) {
            return true;
        }

        return false;
    }

    public function deauthUpdaterSession()
    {
        unset($_SESSION[self::ELIGIBLE_SESSION_KEY]);
        unlink(__DIR__ . '/../config/actions.txt');
    }

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
     * @throws UpdateException
     */
    public function getCurrentVersion()
    {
        $version = file_get_contents('../admin/init.php');
        $matches = array();
        preg_match_all('/define\(\"VERSION\",\"(.*)\"\);/', $version, $matches);

        if (isset($matches[1][0])) {
            return $matches[1][0];
        }

        throw new UpdateException('No production version found.');

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
            $updateMessage = 'Update to ' . htmlentities($versionString) . ' is available.  ';
        } else {
            $updateMessage = 'phpList is up-to-date.';
        }
        if ($this->availableUpdate && isset($serverResponse['autoupdater']) && !($serverResponse['autoupdater'] === 1 || $serverResponse['autoupdater'] === '1')) {
            $this->availableUpdate = false;
            $updateMessage .= '<br />The automatic updater is disabled for this update.';
        }

        return $updateMessage;

    }

    /**
     * Return version data from server
     * @return array
     * @throws \Exception
     */
    private function getResponseFromServer()
    {
        $serverUrl = "https://download.phplist.org/version.json";
        $updateUrl = $serverUrl . '?version=' . $this->getCurrentVersion();

        // create a new cURL resource
        $ch = curl_init();
        // set URL and other appropriate options
        // Disable SSL verification
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        // Will return the response, if false it print the response
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // Set the url
        curl_setopt($ch, CURLOPT_URL, $updateUrl);
        // Execute
        $responseFromServer = curl_exec($ch);
        // Closing
        curl_close($ch);

        // decode json
        $responseFromServer = json_decode($responseFromServer, true);
        return $responseFromServer;
    }

    private function getDownloadUrl()
    {
        // todo: error handling
        $response = $this->getResponseFromServer();
        if (isset($response['url'])) {
            return $response['url'];
        }
        // todo error handling
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
                if (!empty($info->getRealPath())) {
                    $files[] = $info->getRealPath();
                }
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
            'updater' => 1,
            'base' => 1,
            'api.php' =>1,
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
     * Recursively delete a directory and all of it's contents
     *
     * @param string $dir absolute path to directory to delete
     * @return bool
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
                    if (false === unlink($fileinfo)) {
                        throw new \UpdateException("Could not delete $fileinfo");
                    }
                }
            } else {
                if (false === unlink($fileinfo->getRealPath())) {
                    if (false === unlink($fileinfo)) {
                        throw new \UpdateException("Could not delete $fileinfo");
                    }
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
                    continue;
                }

            } else if (is_file($absolutePath)) {
                if (in_array($fileName, $this->excludedFiles)) {
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
     * Get config file path
     * @return string
     */
    function getConfigFilePath()
    {
        return  __DIR__ . '/../config/config.php';
    }

    /**
     * Get a PDO connection
     * @return PDO
     * @throws UpdateException
     */
    function getConnection()
    {
        if (isset($_SERVER['ConfigFile']) && is_file($_SERVER['ConfigFile'])) {
            include $_SERVER['ConfigFile'];

        } elseif (file_exists($this->getConfigFilePath())) {
            include $this->getConfigFilePath();
        } else {
            throw new \UpdateException("Error: Cannot find config file");
        }

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
     * @throws UpdateException
     */
    function addMaintenanceMode()
    {
        if (isset($_SERVER['ConfigFile']) && is_file($_SERVER['ConfigFile'])) {
            include $_SERVER['ConfigFile'];
        } elseif (file_exists($this->getConfigFilePath())) {
            include $this->getConfigFilePath();
        } else {
            throw new \UpdateException("Error: Cannot find config file");
        }
        if (isset($table_prefix)) {
            $table_name = $table_prefix . 'config';
        } else {
            $table_name = 'phplist_config';
        }
        $prepStmt = $this->getConnection()->prepare("SELECT * FROM {$table_name} WHERE item=?");
        $prepStmt->execute(array('update_in_progress'));
        $result = $prepStmt->fetch(PDO::FETCH_ASSOC);
        if ($result === false) {
            // the row does not exist => no update running
            $this->getConnection()
                ->prepare("INSERT INTO {$table_name}(`item`,`editable`,`value`) VALUES (?,0,?)")
                ->execute(array('update_in_progress', 1));
        }
        if ($result['update_in_progress'] == 0) {
            $this->getConnection()
                ->prepare("UPDATE {$table_name} SET `value`=? WHERE `item`=?")
                ->execute(array(1, 'update_in_progress'));
        } else {
            // the row exists and is not 0 => there is an update running
            return false;
        }
        $name = 'maintenancemode';
        $value = "Update process";
        $sql = "UPDATE {$table_name} SET value =?, editable =? where item =? ";
        $this->getConnection()->prepare($sql)->execute(array($value, 0, $name));
    }

    /**
     *Clear the maintenance mode and remove the update_in_progress lock
     * @throws UpdateException
     */
    function removeMaintenanceMode()
    {
        if (isset($_SERVER['ConfigFile']) && is_file($_SERVER['ConfigFile'])) {
            include $_SERVER['ConfigFile'];
        } elseif (file_exists($this->getConfigFilePath())) {
            include $this->getConfigFilePath();
        } else {
            throw new \UpdateException("Error: Cannot find config file");
        }
        if (isset($table_prefix)) {
            $table_name = $table_prefix . 'config';
        } else {
            $table_name = 'phplist_config';
        }
        $name = 'maintenancemode';
        $value = '';
        $sql = "UPDATE {$table_name} SET value =?, editable =? where item =? ";
        $this->getConnection()->prepare($sql)->execute(array($value, 0, $name));
        $this->getConnection()
            ->prepare("UPDATE {$table_name} SET `value`=? WHERE `item`=?")
            ->execute(array(0, "update_in_progress"));
    }

    /**
     * Download and unzip phpList from remote server
     *
     * @throws UpdateException
     */
    function downloadUpdate()
    {
        /** @var string $url */
        $url = $this->getDownloadUrl();
        $zipFile = tempnam(sys_get_temp_dir(), 'phplist-update');
        if ($zipFile === false) {
            throw new UpdateException("Error: Temporary file cannot be created");
        }
        // Get The Zip File From Server
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FAILONERROR, true);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
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
     * Check the downloaded phpList version. Return false if it's a downgrade.
     * @throws UpdateException
     * @return bool
     */
    function checkForDowngrade()
    {
        $downloadedVersion = file_get_contents(self::DOWNLOAD_PATH.'/phplist/public_html/lists/admin/init.php');
        preg_match_all('/define\(\"VERSION\",\"(.*)\"\);/', $downloadedVersion, $matches);

        if (isset($matches[1][0]) && version_compare($this->getCurrentVersion(), $matches[1][0])) {
            return true;
        }
        return false;
    }

    /**
     * Creates temporary dir
     * @throws UpdateException
     */
    function temp_dir()
    {

        $tempdir = mkdir(self::DOWNLOAD_PATH, 0700);
        if ($tempdir === false) {
            throw new UpdateException("Error: Could not create temporary file");
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
            'index.php',
            'lt.php',
            'ut.php',
        );

        foreach ($entryPoints as $key => $fileName) {
            $current = "Update in progress \n";
            $content = file_put_contents(__DIR__ . '/../' . $fileName, $current);
            if ($content === FALSE) {
                throw new UpdateException("Error: Could not write to the $fileName");
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
     * Move new files in place.
     * @throws UpdateException
     */
    function moveNewFiles()
    {
        $rootDir = __DIR__ . '/../tmp_uploaded_update/phplist/public_html/lists';
        $downloadedFiles = scandir($rootDir);
        if (count($downloadedFiles) <= 2) {
            throw new UpdateException("Error: Download folder is empty!");
        }

        foreach ($downloadedFiles as $fileName) {
            if ($this->isExcluded($fileName)) {
                continue;
            }
            $oldFile = $rootDir . '/' . $fileName;
            $newFile = __DIR__ . '/../' . $fileName;
            $state = rename($oldFile, $newFile);
            if ($state === false) {
                throw new UpdateException("Error: Could not move new files");
            }
        }
    }

    /**
     * Move entry points in place.
     */
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
     *  Back up old files to the location specified by the user.
     * @param $destination 'path' to backup zip
     * @throws UpdateException
     */
    function backUpFiles($destination)
    {
        $iterator = new \RecursiveDirectoryIterator(realpath(__DIR__ . '/../'), FilesystemIterator::SKIP_DOTS);
        /** @var SplFileInfo[] $iterator */
        /** @var  $iterator */
        $iterator = new \RecursiveIteratorIterator($iterator, \RecursiveIteratorIterator::SELF_FIRST);

        $zip = new ZipArchive();
        $resZip = $zip->open($destination, ZipArchive::CREATE);
        if ($resZip === false) {
            throw new \UpdateException("Error: Could not create back up of phpList directory. Please make sure that the argument is valid or writable and try again without reloading the page.");
        }
        $zip->addEmptyDir('lists');

        foreach ($iterator as $file) {
            $prefix = realpath(__DIR__ . '/../');
            $name = 'lists/' . substr($file->getRealPath(), strlen($prefix) + 1);
            if ($file->isDir()) {
                $zip->addEmptyDir($name);
                continue;
            }
            if ($file->isFile()) {
                $zip->addFromString($name, file_get_contents($file->getRealPath()));
                continue;
            }
        }
        $state = $zip->close();
        if ($state === false) {
            throw new UpdateException('Error: Could not create back up of phpList directory. Please make sure that the argument is valid or is writable and try again without reloading the page.');
        }

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
            throw new \UpdateException("Error: Unable to open the Zip File");
        }
        /* Extract Zip File */
        $zip->extractTo($extractPath);
        $zip->close();

    }

    /**
     * Delete temporary downloaded files
     * @throws UpdateException
     */
    function deleteTemporaryFiles()
    {
        $isTempDirDeleted = $this->rmdir_recursive(self::DOWNLOAD_PATH);
        if ($isTempDirDeleted === false) {
            throw new \UpdateException("Error: Could not delete temporary files!");
        }

    }

    /**
     * @throws UpdateException
     */
    function recoverFiles()
    {
        $this->unZipFiles('backup.zip', self::DOWNLOAD_PATH);
    }

    /**
     * @param int $action
     * @throws UpdateException
     */
    function writeActions($action)
    {
        $actionsdir = __DIR__ . '/../config/actions.txt';
        if (!file_exists($actionsdir)) {
            $actionsFile = fopen($actionsdir, "w+");
            if ($actionsFile === false) {
                throw new \UpdateException("Error: Could not create actions file in the config directory, please change permissions");
            }
        }
        $written = file_put_contents($actionsdir, json_encode(array('continue' => false, 'step' => $action)));
        if ($written === false) {
            throw new \UpdateException("Error: Could not write on $actionsdir");
        }
    }

    /**
     * Return the current step
     * @return mixed array of json data
     * @throws UpdateException
     */
    function currentUpdateStep()
    {
        $actionsdir = __DIR__ . '/../config/actions.txt';
        if (file_exists($actionsdir)) {
            $status = file_get_contents($actionsdir);
            if ($status === false) {
                throw new \UpdateException("Cannot read content from $actionsdir");
            }
            $decodedJson = json_decode($status, true);
            if (!is_array($decodedJson)) {
                throw new \UpdateException('JSON data cannot be decoded!');
            }

        } else {
            return array('step' => 0, 'continue' => true);
        }
        return $decodedJson;

    }

    /**
     * Check if config folder is writable. Required to be writable in order to write steps.
     */
    function checkConfig()
    {
        $configdir = __DIR__ . '/../config/';
        if (!is_dir($configdir) || !is_writable($configdir)) {
            die("Cannot update because config directory is not writable.");
        }
    }

    /**
     * Check if required php modules are installed.
     */
    function checkphpmodules()
    {

        $phpmodules = array('curl', 'pdo', 'zip');
        $notinstalled = array();

        foreach ($phpmodules as $value) {
            if (!extension_loaded($value)) {
                array_push($notinstalled, $value);
            }
        }
        if (count($notinstalled) > 0) {
            $message = "The following php modules are required. Please install them to continue." . '<br>';
            foreach ($notinstalled as $value) {
                $message .= $value . '<br>';
            }
            die($message);
        }
    }

    /**
     * Move plugins in temporary folder to prevent them from being overwritten.
     * @throws UpdateException
     */
    function movePluginsInTempFolder()
    {
        $oldDir = __DIR__ . '/../admin/plugins';
        $newDir = __DIR__ . '/../tmp_uploaded_update/tempplugins';
        $state = rename($oldDir, $newDir);
        if ($state === false) {
            throw new UpdateException("Could not move plugins directory");
        }
    }

    /**
     * Move any additional plugin files and directories back to the admin directory.
     * @throws UpdateException
     */
    function movePluginsInPlace()
    {
        $oldDir = realpath(__DIR__ . '/../tmp_uploaded_update/tempplugins');
        $newDir = realpath(__DIR__ . '/../admin/plugins');

        $existingPluginFiles = scandir($oldDir);
        $newPluginFiles = scandir($newDir);
        $additional = array_diff($existingPluginFiles, $newPluginFiles);

        foreach ($additional as $file) {
            $state = rename("$oldDir/$file", "$newDir/$file");

            if ($state === false) {
                throw new UpdateException("Could not restore plugin $file.");
            }
        }
    }

    /**
     * Update updater to a new location before temp folder is deleted!
     * @throws UpdateException
     */
    function moveUpdater()
    {
        $rootDir = __DIR__ . '/../tmp_uploaded_update/phplist/public_html/lists';
        $oldFile = $rootDir . '/updater';
        $newFile = __DIR__ . '/../tempupdater';
        $state = rename($oldFile, $newFile);
        if ($state === false) {
            throw new UpdateException("Could not move updater");
        }
    }

    /**
     * Replace new updater as the final step
     * @throws UpdateException
     */
    function replaceNewUpdater()
    {
        $newUpdater = realpath(__DIR__ . '/../tempupdater');
        $oldUpdater = realpath(__DIR__ . '/../updater');

        $this->rmdir_recursive($oldUpdater);
        $state = rename($newUpdater, $oldUpdater);
        if ($state === false) {
            throw new UpdateException("Could not move the new updater in place");
        }
    }
}

try {
    $update = new updater();
    if (!$update->isAuthenticated()) {
        die('No permission to access updater.');
    }
    $update->checkConfig();
    $update->checkphpmodules();

} catch (\UpdateException $e) {
    throw $e;
}

/**
 *
 *
 *
 */
if (isset($_POST['action'])) {
    set_time_limit(0);

    //ensure that $action is integer

    $action = (int)$_POST['action'];

    header('Content-Type: application/json');
    $writeStep = true;
    switch ($action) {
        case 0:
            $statusJson = $update->currentUpdateStep();
            echo json_encode(array('status' => $statusJson, 'autocontinue' => true));
            break;
        case 1:
            $currentVersion = $update->getCurrentVersion();
            $updateMessage = $update->checkIfThereIsAnUpdate();
            $isThereAnUpdate = $update->availableUpdate();
            if ($isThereAnUpdate === false) {
                echo(json_encode(array('continue' => false, 'response' => $updateMessage)));
            } else {
                echo(json_encode(array('continue' => true, 'response' => $updateMessage)));
            }
            break;
        case 2:
            echo(json_encode(array('continue' => true, 'autocontinue' => true, 'response' => 'Starting integrity check')));
            break;
        case 3:
            $unexpectedFiles = $update->checkRequiredFiles();
            if (count($unexpectedFiles) !== 0) {
                $elements = "Error: The following files are either not expected and should be removed, or are missing but required and should be put back in place \n";
                foreach ($unexpectedFiles as $key => $fileName) {
                    $elements .= $key . "\n";
                }
                echo(json_encode(array('retry' => true, 'continue' => false, 'response' => $elements)));
            } else {
                echo(json_encode(array('continue' => true, 'response' => 'Integrity check successful', 'autocontinue' => true)));
            }
            break;
        case 4:
            $notWriteableFiles = $update->checkWritePermissions();
            if (count($notWriteableFiles) !== 0) {
                $notWriteableElements = "Error: No write permission for the following files: \n";;
                foreach ($notWriteableFiles as $key => $fileName) {
                    $notWriteableElements .= $fileName . "\n";
                }
                echo(json_encode(array('retry' => true, 'continue' => false, 'response' => $notWriteableElements)));
            } else {
                echo(json_encode(array('continue' => true, 'response' => 'Write check successful.', 'autocontinue' => true)));
            }
            break;
        case 5:
            echo(json_encode(array('continue' => true, 'response' => 'Do you want a backup? <form><input type="radio" name="create_backup" value="true">Yes<br><input type="radio" name="create_backup" value="false" checked>No</form>')));
            break;
        case 6:
            $createBackup = $_POST['create_backup'];
            if ($createBackup === 'true') {
                echo(json_encode(array('continue' => true, 'response' => 'Choose location where to backup the /lists directory. Please make sure to choose a location outside the web root:<br> <form onsubmit="return false;"><input type="text" id="backuplocation" size="55" name="backup_location" placeholder="/var/backup.zip" /></form>')));
            } else {
                echo(json_encode(array('continue' => true, 'response' => '', 'autocontinue' => true)));
            }
            break;
        case 7:
            $createBackup = $_POST['create_backup'];
            if ($createBackup === 'true') {
                $backupLocation = realpath(dirname($_POST['backup_location']));
                $phplistRootFolder = realpath(__DIR__ . '/../../');
                if (strpos($backupLocation, $phplistRootFolder) === 0) {
                    echo(json_encode(array('retry' => true, 'continue' => false, 'response' => 'Error: Please choose a folder outside of your phpList installation.')));
                    break;
                }
                if (!preg_match("/^.*\.(zip)$/i", $_POST['backup_location'])) {
                    echo(json_encode(array('retry' => true, 'continue' => false, 'response' => 'Error: Please add .zip extension.')));
                    break;
                }
                try {
                    $update->backUpFiles($_POST['backup_location']);
                    echo(json_encode(array('continue' => true, 'response' => 'Backup has been created')));
                } catch (\Exception $e) {
                    echo(json_encode(array('retry' => true, 'continue' => false, 'response' => $e->getMessage())));
                    break;
                }
            } else {
                echo(json_encode(array('continue' => true, 'response' => 'No back up created', 'autocontinue' => true)));
            }

            break;
        case 8:
            echo(json_encode(array('continue' => true, 'autocontinue' => true, 'response' => 'Download in progress')));
            break;
        case 9:
            try {
                $update->downloadUpdate();
                echo(json_encode(array('continue' => true, 'response' => 'The update has been downloaded!')));
            } catch (\Exception $e) {
                echo(json_encode(array('continue' => false, 'response' => $e->getMessage())));
            }
            break;
        case 10:
            if ($update -> checkForDowngrade()) {
                echo (json_encode(array('continue' => true, 'autocontinue' => true, 'response' => 'Not a downgrade!')));
            } else {
                echo(json_encode(array('continue' => false, 'response' => 'Downgrade is not supported.')));
            }
            break;
        case 11:
            $on = $update->addMaintenanceMode();
            if ($on === false) {
                echo(json_encode(array('continue' => false, 'response' => 'Cannot set the maintenance mode on!')));
            } else {
                echo(json_encode(array('continue' => true, 'response' => 'Set maintenance mode on', 'autocontinue' => true)));
            }
            break;
        case 12:
            try {
                $update->replacePHPEntryPoints();
                echo(json_encode(array('continue' => true, 'response' => 'Replaced entry points', 'autocontinue' => true)));
            } catch (\Exception $e) {
                echo(json_encode(array('continue' => false, 'response' => $e->getMessage())));
            }
            break;
        case 13:
            try {
                $update->movePluginsInTempFolder();
                echo(json_encode(array('continue' => true, 'response' => 'Backing up the plugins', 'autocontinue' => true)));
            } catch (\Exception $e) {
                echo(json_encode(array('continue' => false, 'response' => $e->getMessage())));
            }
            break;
        case 14:
            try {
                $update->deleteFiles();
                echo(json_encode(array('continue' => true, 'response' => 'Old files have been deleted!', 'autocontinue' => true)));
            } catch (\Exception $e) {
                echo(json_encode(array('continue' => false, 'response' => $e->getMessage())));
            }
            break;
        case 15:
            try {
                $update->moveNewFiles();
                echo(json_encode(array('continue' => true, 'response' => 'Moved new files in place!', 'autocontinue' => true)));

            } catch (\Exception $e) {
                echo(json_encode(array('continue' => false, 'response' => $e->getMessage())));
            }
            break;
        case 16:
            try {
                $update->movePluginsInPlace();
                echo(json_encode(array('continue' => true, 'response' => 'Moved plugins in place!', 'autocontinue' => true)));
            } catch (\Exception $e) {
                echo(json_encode(array('continue' => false, 'response' => $e->getMessage())));
            }
            break;
        case 17:
            try {
                $update->moveEntryPHPpoints();
                echo(json_encode(array('continue' => true, 'response' => 'Moved new entry points in place!', 'autocontinue' => true)));
            } catch (\Exception $e) {
                echo(json_encode(array('continue' => false, 'response' => $e->getMessage())));
            }
            break;
        case 18:
            try {
                $update->moveUpdater();
                echo(json_encode(array('continue' => true, 'response' => 'Moved new entry points in place!', 'autocontinue' => true)));
            } catch (\Exception $e) {
                echo(json_encode(array('continue' => false, 'response' => $e->getMessage())));
            }
            break;
        case 19:
            try {
                $update->deleteTemporaryFiles();
                echo(json_encode(array('continue' => true, 'response' => 'Deleted temporary files!', 'autocontinue' => true)));
            } catch (\Exception $e) {
                echo(json_encode(array('continue' => false, 'response' => $e->getMessage())));
            }
            break;
        case 20:
            try {
                $update->removeMaintenanceMode();
                echo(json_encode(array('continue' => true, 'response' => 'Removed maintenance mode', 'autocontinue' => true)));
            } catch (\Exception $e) {
                echo(json_encode(array('continue' => false, 'response' => $e->getMessage())));
            }
            break;
        case 21:
            $writeStep = false;
            try {
                $update->replaceNewUpdater();
                $update->deauthUpdaterSession();
                echo(json_encode(array('continue' => true, 'nextUrl' => '../admin/', 'response' => 'Updated successfully.')));
            } catch (\Exception $e) {
                echo(json_encode(array('continue' => false, 'response' => $e->getMessage())));
            }
            break;
    };

    if ($writeStep) {
        try {
            $update->writeActions($action - 1);
        } catch (\Exception $e) {

        }
    }
} else {
    ?>

    <html>
    <head>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link href="https://fonts.googleapis.com/css?family=Source+Sans+Pro" rel="stylesheet">


        <style>
            /* http://meyerweb.com/eric/tools/css/reset/
               v2.0 | 20110126
               License: none (public domain)
            */
            html, body, div, span, applet, object, iframe,
            h1, h2, h3, h4, h5, h6, p, blockquote, pre,
            a, abbr, acronym, address, big, cite, code,
            del, dfn, em, img, ins, kbd, q, s, samp,
            small, strike, strong, sub, sup, tt, var,
            b, u, i, center,
            dl, dt, dd, ol, ul, li,
            fieldset, form, label, legend,
            table, caption, tbody, tfoot, thead, tr, th, td,
            article, aside, canvas, details, embed,
            figure, figcaption, footer, header, hgroup,
            menu, nav, output, ruby, section, summary,
            time, mark, audio, video {
                margin: 0;
                padding: 0;
                border: 0;
                font-size: 100%;
                font: inherit;
                vertical-align: baseline;
            }

            /* HTML5 display-role reset for older browsers */
            article, aside, details, figcaption, figure,
            footer, header, hgroup, menu, nav, section {
                display: block;
            }

            body {
                line-height: 1;
            }

            ol, ul {
                list-style: none;
            }

            blockquote, q {
                quotes: none;
            }

            blockquote:before, blockquote:after,
            q:before, q:after {
                content: '';
                content: none;
            }

            table {
                border-collapse: collapse;
                border-spacing: 0;
            }

            /** phpList CSS **/
            body {
                background-color: #FAFAFA;
                font-family: 'Source Sans Pro', sans-serif;
                margin-top: 50px;
            }

            button.right {
                background-color: #21AE8A;
                color: white;
                border-radius: 5px;
                height: 40px;
                padding-left: 30px;
                padding-right: 30px;
                font-size: 15px;
                text-transform: uppercase;
                margin-top: 20px;
                border: none;
                font-family: "Montserrat", SemiBold;
                font-weight:600;
            }

            button:disabled {
                background-color: lightgrey !important;
            }

            .right {
                float: right;
            }

            @media only screen and (min-width: 1200px) {
                #center {
                    margin: auto;
                    width: 70%;
                }
            }

            @media only screen and (max-width: 350px) {
                #steps {
                    width: 100% !important;
                }
            }

            @media only screen and (max-width: 800px) {
                #center {
                    width: 100%;
                }

                .divider {
                    visibility: hidden;
                }
            }

            @media only screen and (min-width: 800px) and (max-width: 1200px) {
                #center {
                    margin: auto;
                    width: 90%;
                }
            }

            @media only screen and (min-width: 1200px) and (max-width: 1400px) {

                #center {
                    margin: auto;
                    max-width: 75%;
                }

                #display {
                    max-width: 70%;
                    margin: 0 auto;
                }

            }

            @media only screen and (min-width: 890px) and (max-width: 1100px) {

                #container {
                    width: 80%;
                    margin: 0 auto;
                }
            }

            @media only screen and (min-width: 1101px) {

                #container {
                    width: 60%;
                    margin: 0 auto;
                }
            }

            @media only screen and (min-width: 700px) and (max-width: 889px) {

                #container {
                    width: 95%;
                    margin: 0 auto;
                }
            }


            #display {
                background-color: white;
                padding-left: 20px;
                padding-top: 20px;
                padding-bottom: 20px;
                border-radius: 12px;
                width: 80%;
                margin: 0 auto;
            }

            #logo {
                color: #8C8C8C;
                font-size: 20px;
                text-align: center;
                margin-bottom: 50px;
                cursor: pointer;
            }

            #logo img {
                margin-bottom: 20px;
            }

            #logo h1 {
                margin-top: 34px;
            }

            #steps h2 {
                font-size: 15px;
                color: #8C8C8C;
                width: 50%;
                text-align: center;
                margin-left: 6px;
                display: flex;
            }

            #steps {
                width: 64%;
                margin: auto;
                padding-bottom: 27px;
            }

            #first-step {
                width: calc((25% - 70px) / 2) !important;
                float: left;
                height: 1px;
            }

            .step {
                width: 25%;
                float: left;
            }

            .last-step {
                width: 70px;
            }

            .step-image {
                width: 64px;
                height: 64px;
                border-radius: 100px;
                margin-bottom: 12px;
                float: left;
                background-color: #fff;
                box-shadow: 0px 3px 6px rgba(0, 0, 0, 0.14);
            }

            .step-image svg {
                width: 50%;
                padding-top: 32%;
                padding-left: 24%;
            }

            .active {
                background-color: #56A3D2;
                border: 0;
            }

            .active svg path {
                fill: white;
            }

            .clear {
                clear: both;
            }

            .divider {
                border-top: 1px dashed #253746;
                width: inherit;
                margin-top: 30px;
            }

            .hidden {
                display: none;
            }

            i {
                border: solid #ffffff;
                border-width: 0 2px 2px 0;
                display: inline-block;
                padding: 3px;
            }

            .option-heading:before {
                content: "\25bc";
            }

            .option-heading.is-active:before {
                content: "\25b2";
            }

            /* Helpers */
            .is-hidden {
                display: block;
                background-color: rgb(255, 255, 255);
            }

            #footer_updater {
                position: absolute;
                bottom: 0px;
                height: 20px;
            }

            .option-heading {
                color: #fff;
                background: #4B8CCA;
                /* padding: 3px 17px; */
                border-radius: 5px;
                width: 50px;
                height: 19px;
                padding-top: 7px;
                display: block;
            }

            ul li {
                color: #8A9798;
                margin-bottom: 8px;
                display: flex;
                font-size: 14px;
            }

            #pointsList span {
                font-family: 'Source Sans Pro', Light;
            }

            li.final {
                color: #4B8CCA;
                font-family: Montserrat, Sans-Serif;
                font-size: 24px;
                letter-spacing: 0.3px;
                margin-bottom: 9px;
            }

            li.migrate a {
                color: #253746;
                font-family: Montserrat, SemiBold;
                font-size: 18px;
                letter-spacing: 0.3px;
                margin-bottom: 20px;
                text-decoration: none;
            }

            #success-message {
                font-size: 14px;
                font-family: Source Sans Pro, Light;
                line-height: 22px;
                color: #2C2C2C;
            }

            #next-step {
                margin-top: 40px;
            }

            .outer {
                /*position: absolute;*/
                position: fixed;
                bottom: 0px;
                width: 100%;
                margin: 0px auto;
                text-align: center;
            }

            .inner {
                display: none;
                background-color: #fff;
                box-shadow: 9px 5px 6px 4px rgba(0, 0, 0, 0.16);
            }

            button.info-footer {
                background-color: #4B8CCA;
                color: white;
                margin-top: 0px;
                border: none;
                width: 50px;
                height: 25px;
                border-radius: 5px 5px 0px 0px;
                box-shadow: none;
            }

            #sqr {
                width:225px;
                height:180px;
                background-image: url(images/square.svg);
                background-repeat: no-repeat;
            }

            #triangle_down {
                width: 0;
                height: 0;
                border-top: 140px solid #20a3bf;
                border-left: 70px solid transparent;
                border-right: 70px solid transparent;
            }

            input.book {
                width: 90px;
                height: 30px;
                border: 1px dashed #21AE8A;
                background: #fff;
                margin: 0 auto;
                color: #21AE8A;
                text-transform: uppercase;
                font-size: 12px;
                font-weight: 600;
                cursor: pointer;
                font-family: "Montserrat", SemiBold;
            }

            #database-upgrade.right {
                margin-top: 40px;
                font-size: 12px;
                padding: 1px 10px;
            }

            .listItems {
                background-image: url('images/check.svg');
                background-repeat: no-repeat;
            }

            #pointsList img {
                margin-right: 6px;
            }

            .container {
                text-align: center;
            }

            p.greatValue {
                float: left;
                color: rgb(75, 140, 202);
                font-weight: 500;
                font-size: 10px;
                margin-left: 23px;
                margin-top: 29px;
                font-family: "Source Sans Pro", Regular;
            }

            p.messages {
                text-align: center;
                color: #253746;
                margin-top: 35px;
                font-size: 14px;
                font-family: 'Source Sans Pro', Light;
            }

            p.price {
                text-align: center;
                color: #4B8CCA;
                font-size: 24px;
                font-family: 'Montserrat', Regular;
                line-height: 7px;
                margin-top: 5px;
            }

            p.subscribers {
                text-align: center;
                margin-top: 14px;
                margin-bottom: 10px;
                font-size: 12px;
                font-family: 'Source Sans Pro', Regular;
            }

            #wrap {
                text-align: center;
                width: 100%;
            }

            #left {
                display: inline-block;
                margin-top: 3px;
                margin-right: 50px;
                padding: 20px 15px;
                width: 498px;
            }

            #right {
                display: inline-block;
                margin-top: 3px;
                padding-bottom: 20px;
            }

            div.cutomMinHeight {
                min-height: 900px !important;
            }
            p.paidSupport {
                color: #8A9798;
                font-size: 14px;
                margin-top: 14px;
                font-family: 'Source Sans Pro', Light;

            }
            a.support {
                color: #4b8cca;
                text-decoration: none;
            }
            svg.performUpdate {
                padding-top: 39%;
                padding-left: 28%;
            }

            #arrowdown {
                width: 21px;
                height: 12px;
                background-image: url(images/arrow_down.png);
                background-repeat: no-repeat;
                margin: 0 auto;
                -moz-transition: all 1.5s ease-out;
                -webkit-transition: all 1.5s ease-out;
                -o-transition: all 1.5s ease-out;
                transition: all 1.5s ease-out;
            }
        </style>
    </head>
    <body>

    <div id="center">
        <div class="fixed">
            <div id="logo" title="Go back to phpList dashboard" onclick="location.href='../admin';">
                <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="175"
                     height="54.598" viewBox="0 0 175 54.598">
                    <defs>
                        <linearGradient id="linear-gradient" x1="0.763" y1="0.237" x2="0.257" y2="0.745"
                                        gradientUnits="objectBoundingBox">
                            <stop offset="0" stop-color="#fff"/>
                            <stop offset="0.15" stop-color="#fbfcfc"/>
                            <stop offset="0.28" stop-color="#f0f2f3"/>
                            <stop offset="0.42" stop-color="#dee1e3"/>
                            <stop offset="0.55" stop-color="#c3c9cd"/>
                            <stop offset="0.67" stop-color="#a1aab0"/>
                            <stop offset="0.8" stop-color="#78848d"/>
                            <stop offset="0.92" stop-color="#485865"/>
                            <stop offset="1" stop-color="#233746"/>
                        </linearGradient>
                        <linearGradient id="linear-gradient-2" x1="0.236" y1="0.778" x2="0.717" y2="0.294"
                                        gradientUnits="objectBoundingBox">
                            <stop offset="0" stop-color="#fff"/>
                            <stop offset="0.15" stop-color="#fbfcfc"/>
                            <stop offset="0.29" stop-color="#f0f2f3"/>
                            <stop offset="0.42" stop-color="#dee0e3"/>
                            <stop offset="0.55" stop-color="#c3c8cd"/>
                            <stop offset="0.67" stop-color="#a1aab0"/>
                            <stop offset="0.8" stop-color="#78848d"/>
                            <stop offset="0.92" stop-color="#485864"/>
                            <stop offset="1" stop-color="#243746"/>
                        </linearGradient>
                    </defs>
                    <g id="Artwork_3" data-name="Artwork 3" transform="translate(87.5 27.299)">
                        <g id="Artwork_3-2" data-name="Artwork 3" transform="translate(-87.5 -27.299)">
                            <g id="Group_343" data-name="Group 343" transform="translate(62.756 10.828)">
                                <g id="Group_342" data-name="Group 342" transform="translate(106.498)">
                                    <path id="Path_799" data-name="Path 799"
                                          d="M541.063,41.334v-.413a1.175,1.175,0,0,0-.064-.444.571.571,0,0,0-.286-.286h0a.6.6,0,0,0,.349-.54.762.762,0,0,0-.286-.635,1.333,1.333,0,0,0-.825-.222H539v2.825h.444V40.381h.6a.6.6,0,0,1,.413.127.54.54,0,0,1,.127.413,4.921,4.921,0,0,0,.032.54v.1h.476v-.19Zm-.444-1.778a.381.381,0,0,1-.19.317.921.921,0,0,1-.476.127h-.508V39.08h.54a.825.825,0,0,1,.476.127A.445.445,0,0,1,540.619,39.556Z"
                                          transform="translate(-537.159 -37.306)" fill="#243746"/>
                                    <path id="Path_800" data-name="Path 800"
                                          d="M538.564,35.5a2.73,2.73,0,0,0-1.048-1.016,2.952,2.952,0,0,0-2.889,0,2.73,2.73,0,0,0-1.048,1.016,2.825,2.825,0,0,0,1.048,3.873,2.952,2.952,0,0,0,2.889,0,2.825,2.825,0,0,0,1.048-3.873Zm-2.476,3.873a2.444,2.444,0,0,1-1.27-.349,2.381,2.381,0,0,1-.889-.889,2.445,2.445,0,0,1-.317-1.206,2.413,2.413,0,0,1,.317-1.238,2.349,2.349,0,0,1,.889-.889,2.476,2.476,0,0,1,1.238-.317,2.444,2.444,0,0,1,1.27.349,2.381,2.381,0,0,1,.889.889,2.476,2.476,0,0,1,0,2.412,2.381,2.381,0,0,1-.889.889,2.444,2.444,0,0,1-1.238.349Z"
                                          transform="translate(-533.2 -34.111)" fill="#243746"/>
                                </g>
                                <path id="Path_801" data-name="Path 801"
                                      d="M274.011,50.491a7.072,7.072,0,0,1,1.809,5.269v9.079h-4.6v-8.38a4.208,4.208,0,0,0-.825-2.825,2.9,2.9,0,0,0-2.381-.921,3.65,3.65,0,0,0-2.762,1.079A4.432,4.432,0,0,0,264.2,57v7.841h-4.6V47.6a4.6,4.6,0,0,1,4.6-4.6h0v7.618a6.126,6.126,0,0,1,2.222-1.4,8,8,0,0,1,2.825-.508A6.5,6.5,0,0,1,274.011,50.491Z"
                                      transform="translate(-239.951 -40.178)" fill="#243746"/>
                                <path id="Path_802" data-name="Path 802"
                                      d="M384.493,47.3V63.87h10.348v3.9H379.7V52.093A4.793,4.793,0,0,1,384.493,47.3Z"
                                      transform="translate(-321.927 -43.113)" fill="#243746"/>
                                <path id="Path_803" data-name="Path 803"
                                      d="M433.614,47.821a2.886,2.886,0,0,1,4.127-4.031,2.6,2.6,0,0,1,.794,1.936,2.889,2.889,0,0,1-.794,2.1,3.016,3.016,0,0,1-4.127.032Z"
                                      transform="translate(-358.161 -40.175)" fill="#243746"/>
                                <rect id="Rectangle_185" data-name="Rectangle 185" width="4.603" height="13.888"
                                      transform="translate(75.231 10.757)" fill="#243746"/>
                                <path id="Path_804" data-name="Path 804"
                                      d="M334.184,64.171a7.3,7.3,0,0,0-2.857-2.857A8.1,8.1,0,0,0,327.3,60.3a6.11,6.11,0,0,0-4.984,2.063V60.3a4.6,4.6,0,0,0-4.412,4.635v17.2h4.6V74.71a6.179,6.179,0,0,0,4.793,1.9,8.1,8.1,0,0,0,4.031-1.016,7.3,7.3,0,0,0,2.857-2.857,9.289,9.289,0,0,0,0-8.571Zm-4.7,7.11a3.873,3.873,0,0,1-2.92,1.174,3.987,3.987,0,1,1,2.92-6.857,3.682,3.682,0,0,1,.794,1.143,5.4,5.4,0,0,1,.254,1.746,5.9,5.9,0,0,1-.127,1.143,3.9,3.9,0,0,1-.921,1.651Z"
                                      transform="translate(-279.745 -51.985)" fill="#243746"/>
                                <path id="Path_805" data-name="Path 805"
                                      d="M469.477,68.863a4.761,4.761,0,0,0-2.159-1.333,27.173,27.173,0,0,0-3.174-.7,14.188,14.188,0,0,1-2.539-.571,1.048,1.048,0,0,1-.794-1.048,1.206,1.206,0,0,1,.73-1.048,4.655,4.655,0,0,1,2.222-.413,9.205,9.205,0,0,1,4.571,1.206l1.524-3.27a9.364,9.364,0,0,0-2.762-1.016,15.459,15.459,0,0,0-3.365-.381,10.856,10.856,0,0,0-3.936.635,5.619,5.619,0,0,0-2.539,1.809A4.349,4.349,0,0,0,456.4,65.4a3.818,3.818,0,0,0,.921,2.762,4.984,4.984,0,0,0,2.19,1.365,23.2,23.2,0,0,0,3.206.667,11.776,11.776,0,0,1,2.444.508,1.017,1.017,0,0,1,.794.984q0,1.46-2.92,1.46a10.793,10.793,0,0,1-2.952-.413,10.127,10.127,0,0,1-2.159-.889l-1.524,3.3a11.174,11.174,0,0,0,2.6.984,15.4,15.4,0,0,0,3.873.476,11.491,11.491,0,0,0,4.031-.635,5.65,5.65,0,0,0,2.571-1.778,4.19,4.19,0,0,0,.889-2.635A3.687,3.687,0,0,0,469.477,68.863Z"
                                      transform="translate(-374.279 -51.98)" fill="#243746"/>
                                <path id="Path_806" data-name="Path 806"
                                      d="M215.032,68.457a8.634,8.634,0,0,0-1.048-4.285,7.3,7.3,0,0,0-2.857-2.857A8.1,8.1,0,0,0,207.1,60.3a6.109,6.109,0,0,0-4.984,2.063V60.3a4.6,4.6,0,0,0-4.412,4.635v17.2h4.6V74.71a6.179,6.179,0,0,0,4.793,1.9,8.094,8.094,0,0,0,4.031-1.016,7.3,7.3,0,0,0,2.857-2.857,8.634,8.634,0,0,0,1.048-4.285Zm-12.761,0h0a3.873,3.873,0,0,1,4.031-3.968,4,4,0,0,1,2.92,1.111,3.619,3.619,0,0,1,1.079,2.1,6.188,6.188,0,0,1,.063.7,4.1,4.1,0,0,1-1.111,2.92A4.022,4.022,0,0,1,202.3,68.52Z"
                                      transform="translate(-197.7 -51.985)" fill="#243746"/>
                                <path id="Path_807" data-name="Path 807"
                                      d="M514.988,66.8a2.952,2.952,0,0,1-1.873.6,1.9,1.9,0,0,1-1.46-.54,2.159,2.159,0,0,1-.508-1.555V59.623h3.714a3.555,3.555,0,0,0-1.047-2.508h0a3.968,3.968,0,0,0-2.666-1.016h0l.1-4h-4.6l-.1,4h-.667a1.778,1.778,0,1,0,0,3.555h.667v5.682A5.555,5.555,0,0,0,508.1,69.59a6.154,6.154,0,0,0,4.381,1.46,8.476,8.476,0,0,0,2.1-.254,4.761,4.761,0,0,0,1.682-.762Z"
                                      transform="translate(-406.839 -46.39)" fill="#243746"/>
                            </g>
                            <g id="Group_345" data-name="Group 345">
                                <circle id="Ellipse_63" data-name="Ellipse 63" cx="27.299" cy="27.299" r="27.299"
                                        fill="#243746"/>
                                <path id="Path_808" data-name="Path 808"
                                      d="M41.283,40.462l2.666-2.635.222-.222a6.953,6.953,0,0,0,.667-.794,7.364,7.364,0,1,0-12.126-.349,6.158,6.158,0,0,0,.825,1.079l.317.317,2.6,2.6-5.65,5.555L12.9,28.146A27.711,27.711,0,0,0,11,31.034L28.363,48.4l-2.571,2.539-.381.381a7.237,7.237,0,0,0-2,4.6h0a6.912,6.912,0,0,0,1.238,4.508,7.364,7.364,0,0,0,13.078-1.778,6.285,6.285,0,0,0,.349-2.7,7.364,7.364,0,0,0-2.127-4.825l-2.762-2.762,5.682-5.523L56.742,60.714a27.139,27.139,0,0,0,1.873-2.92ZM33.538,53.6a3.968,3.968,0,1,1-5.714.127l.286-.317,2.635-2.6Zm2.444-18.411a3.968,3.968,0,1,1,5.777.1l-.127.127L38.87,38.081Z"
                                      transform="translate(-7.508 -17.131)" fill="#fff"/>
                                <g id="Group_344" data-name="Group 344" transform="translate(18.062 18.189)">
                                    <path id="Path_809" data-name="Path 809"
                                          d="M104.006,59.712l-.127.1-2.666,2.635L98.8,60.062l2.73-2.7.063-.063Z"
                                          transform="translate(-85.5 -57.3)" fill="url(#linear-gradient)"/>
                                    <path id="Path_810" data-name="Path 810"
                                          d="M62.074,100.844l-2.635,2.571-.159.127L56.9,101.162l.222-.222L59.693,98.4Z"
                                          transform="translate(-56.9 -85.354)" fill="url(#linear-gradient-2)"/>
                                </g>
                            </g>
                        </g>
                    </g>
                </svg>

                <h1 style="font-family: 'Montserrat', Regular;font-size: 18px;cursor:auto;">Updating phpList to the latest
                    version</h1>
            </div>
            <div id="steps">
                <div id="first-step"></div>
                <div class="step">
                    <div class="step-image active">

                        <svg xmlns="http://www.w3.org/2000/svg" width="27.483" height="25.403"
                             viewBox="0 0 27.483 25.403">
                            <g id="Integrity_check" data-name="Integrity check" transform="translate(0 0)">
                                <g id="Group_211" data-name="Group 211">
                                    <g id="Path_218" data-name="Path 218" transform="translate(0 -7.524)" fill="#fff">
                                        <path d="M23.808,16.226v-.994l3.674-.171V13.218l-3.674-.171v-.765H20.935V8.911A1.388,1.388,0,0,0,19.55,7.524H7.932A1.388,1.388,0,0,0,6.546,8.911v3.372H3.674v.765L0,13.218v1.844l3.674.171v.994H6.546v2.142H3.674v.763L0,19.3v1.843l3.674.172v.994H6.546v2.372H3.674v.765L0,25.619v1.843l3.674.171v.994H6.546v2.914a1.387,1.387,0,0,0,1.386,1.385H19.55a1.386,1.386,0,0,0,1.385-1.385V28.627h2.873v-.994l3.674-.171V25.619l-3.674-.171v-.765H20.935V22.311h2.873v-.994l3.674-.172V19.3l-3.674-.171v-.763H20.935V16.226Zm2.946,10.087v.452l-3.674.171v.96H21.05V25.412h2.03v.732ZM23.08,20.625v.959H21.05V19.1h2.03v.732L26.754,20v.454Zm3.674-6.71v.453l-3.674.171v.96H21.05V13.011h2.03v.732ZM19.55,32.2H7.932a.658.658,0,0,1-.657-.656V8.911a.658.658,0,0,1,.657-.658H19.55a.658.658,0,0,1,.656.658v22.63A.657.657,0,0,1,19.55,32.2ZM.728,26.766v-.452L4.4,26.143v-.732h2.03V27.9H4.4v-.96Zm0-6.313V20L4.4,19.828V19.1h2.03v2.486H4.4v-.959Zm0-6.086v-.453L4.4,13.743v-.732h2.03V15.5H4.4v-.96Z"
                                              stroke="none"/>
                                        <path d="M 7.931596755981445 7.524005889892578 L 19.55012512207031 7.524005889892578 C 20.31339645385742 7.524005889892578 20.93525695800781 8.146415710449219 20.93525695800781 8.91064453125 L 20.93525695800781 12.28284454345703 L 23.80833625793457 12.28284454345703 L 23.80833625793457 13.04763412475586 L 27.48283576965332 13.21833419799805 L 27.48283576965332 15.06194496154785 L 23.80833625793457 15.23264503479004 L 23.80833625793457 16.22646522521973 L 20.93525695800781 16.22646522521973 L 20.93525695800781 18.36890411376953 L 23.80833625793457 18.36890411376953 L 23.80833625793457 19.13216400146484 L 27.48283576965332 19.30328559875488 L 27.48283576965332 21.14592552185059 L 23.80833625793457 21.31759452819824 L 23.80833625793457 22.31141471862793 L 20.93525695800781 22.31141471862793 L 20.93525695800781 24.68343353271484 L 23.80833625793457 24.68343353271484 L 23.80833625793457 25.44821548461914 L 27.48283576965332 25.61934471130371 L 27.48283576965332 27.4625358581543 L 23.80833625793457 27.6336555480957 L 23.80833625793457 28.62747573852539 L 20.93525695800781 28.62747573852539 L 20.93525695800781 31.54160499572754 C 20.93525695800781 32.3063850402832 20.31339645385742 32.92672348022461 19.55012512207031 32.92672348022461 L 7.931735992431641 32.92672348022461 C 7.167505264282227 32.92672348022461 6.545646667480469 32.3063850402832 6.545646667480469 31.54160499572754 L 6.545646667480469 28.62747573852539 L 3.673526763916016 28.62747573852539 L 3.673526763916016 27.6336555480957 L -3.814697265625e-06 27.4625358581543 L -3.814697265625e-06 25.61934471130371 L 3.673526763916016 25.4482250213623 L 3.673526763916016 24.68344497680664 L 6.545646667480469 24.68344497680664 L 6.545646667480469 22.31141471862793 L 3.673526763916016 22.31141471862793 L 3.673526763916016 21.31759452819824 L -3.814697265625e-06 21.14592552185059 L -3.814697265625e-06 19.30328559875488 L 3.673526763916016 19.13216400146484 L 3.673526763916016 18.36890411376953 L 6.545646667480469 18.36890411376953 L 6.545646667480469 16.22646522521973 L 3.673526763916016 16.22646522521973 L 3.673526763916016 15.23264503479004 L -3.814697265625e-06 15.06194496154785 L -3.814697265625e-06 13.21833419799805 L 3.673526763916016 13.04763412475586 L 3.673526763916016 12.28284454345703 L 6.545505523681641 12.28284454345703 L 6.545505523681641 8.91064453125 C 6.545505523681641 8.146274566650391 7.167366027832031 7.524005889892578 7.931596755981445 7.524005889892578 Z M 19.55012512207031 32.19705581665039 C 19.91213607788086 32.19705581665039 20.20654678344727 31.90222549438477 20.20640563964844 31.54063415527344 L 20.20640563964844 8.91064453125 C 20.20640563964844 8.547115325927734 19.91157531738281 8.252296447753906 19.54998588562012 8.252296447753906 L 7.931596755981445 8.252296447753906 C 7.569036483764648 8.252296447753906 7.274215698242188 8.547115325927734 7.274215698242188 8.91064453125 L 7.274215698242188 31.54063415527344 C 7.274215698242188 31.90222549438477 7.569036483764648 32.19705581665039 7.931596755981445 32.19705581665039 L 19.55012512207031 32.19705581665039 Z M 23.07963562011719 15.49775505065918 L 23.07963562011719 14.5380744934082 L 26.75412559509277 14.3669548034668 L 26.75412559509277 13.91428375244141 L 23.07963562011719 13.74261474609375 L 23.07963562011719 13.01100540161133 L 21.04997634887695 13.01100540161133 L 21.04997634887695 15.49775505065918 L 23.07963562011719 15.49775505065918 Z M 6.432306289672852 15.49830436706543 L 6.432306289672852 13.01155471801758 L 4.402095794677734 13.01155471801758 L 4.402095794677734 13.7431640625 L 0.7275962829589844 13.91428375244141 L 0.7275962829589844 14.3669548034668 L 4.402095794677734 14.53863525390625 L 4.402095794677734 15.49830436706543 L 6.432306289672852 15.49830436706543 Z M 6.432306289672852 21.58229446411133 L 6.432306289672852 19.09609413146973 L 4.402095794677734 19.09609413146973 L 4.402095794677734 19.82825469970703 L 0.7275962829589844 19.99937438964844 L 0.7275962829589844 20.45288467407227 L 4.402095794677734 20.62358474731445 L 4.402095794677734 21.58229446411133 L 6.432306289672852 21.58229446411133 Z M 23.07963562011719 21.58325386047363 L 23.07963562011719 20.62455558776855 L 26.75412559509277 20.4539852142334 L 26.75412559509277 20.00033378601074 L 23.07963562011719 19.82922554016113 L 23.07963562011719 19.09705543518066 L 21.04997634887695 19.09705543518066 L 21.04997634887695 21.58325386047363 L 23.07963562011719 21.58325386047363 Z M 6.432306289672852 27.89696502685547 L 6.432306289672852 25.41118431091309 L 4.402095794677734 25.41118431091309 L 4.402095794677734 26.14278411865234 L 0.7275962829589844 26.31391525268555 L 0.7275962829589844 26.76603507995605 L 4.402095794677734 26.93673515319824 L 4.402095794677734 27.89696502685547 L 6.432306289672852 27.89696502685547 Z M 23.07963562011719 27.89738464355469 L 23.07963562011719 26.93715476989746 L 26.75412559509277 26.76603507995605 L 26.75412559509277 26.31391525268555 L 23.07963562011719 26.14320373535156 L 23.07963562011719 25.41159439086914 L 21.04997634887695 25.41159439086914 L 21.04997634887695 27.89738464355469 L 23.07963562011719 27.89738464355469 Z"
                                              stroke="none" fill="#707070"/>
                                    </g>
                                    <g id="Ellipse_53" data-name="Ellipse 53" transform="translate(8.193 1.813)"
                                       fill="#fff"
                                       stroke="#707070" stroke-width="1">
                                        <circle cx="0.9" cy="0.9" r="0.9" stroke="none"/>
                                        <circle cx="0.9" cy="0.9" r="0.4" fill="none"/>
                                    </g>
                                    <g id="Ellipse_54" data-name="Ellipse 54" transform="translate(8.193 5.813)"
                                       fill="#fff"
                                       stroke="#707070" stroke-width="1">
                                        <circle cx="0.9" cy="0.9" r="0.9" stroke="none"/>
                                        <circle cx="0.9" cy="0.9" r="0.4" fill="none"/>
                                    </g>
                                    <g id="Ellipse_55" data-name="Ellipse 55" transform="translate(17.193 21.813)"
                                       fill="#fff" stroke="#707070" stroke-width="1">
                                        <circle cx="0.9" cy="0.9" r="0.9" stroke="none"/>
                                        <circle cx="0.9" cy="0.9" r="0.4" fill="none"/>
                                    </g>
                                </g>
                            </g>
                        </svg>

                    </div>
                    <hr class="divider"/>
                    <div class="clear"></div>
                    <h2>Initialize</h2>
                </div>

                <div class="step">
                    <div class="step-image ">

                        <svg xmlns="http://www.w3.org/2000/svg" width="27.337" height="27.637"
                             viewBox="0 0 27.337 27.637">
                            <g id="Replace_files" data-name="Replace files" transform="translate(0 0)">
                                <path id="Path_214" data-name="Path 214"
                                      d="M48.3,15.4c.01-.012.019-.025.028-.038v0c.008-.013.016-.026.023-.039l.006-.012.015-.032.005-.012c.006-.015.011-.03.016-.045v0c0-.014.008-.029.011-.044l0-.013c0-.012,0-.024.005-.035s0-.009,0-.014,0-.032,0-.048V8.615c0-.016,0-.032,0-.048s0-.009,0-.014,0-.024-.005-.036l0-.013c0-.015-.007-.03-.011-.044v0c0-.015-.01-.03-.016-.045L48.37,8.4l-.015-.032-.006-.012c-.007-.013-.015-.026-.023-.039v0c-.009-.013-.018-.026-.028-.038l-.009-.011-.024-.027-.006-.006L40.181.157a.54.54,0,0,0-.763,0L29.946,9.629H26.669a.54.54,0,1,0,0,1.08h2.2l-2.983,2.983H22.821a.54.54,0,1,0,0,1.08H24.8l-.642.642a.54.54,0,0,0,0,.763l1.578,1.578H23.777a.54.54,0,1,0,0,1.08h3.042L29.8,21.817H26.669a.54.54,0,1,0,0,1.08h4.212l4.581,4.581a.54.54,0,0,0,.763,0L48.257,15.447l.005-.006.025-.027L48.3,15.4ZM45.413,11.84l1.922-1.922v3.845ZM35.844,26.333,32.408,22.9h1.643a.54.54,0,0,0,0-1.08H31.329l-2.983-2.983h1.371a.54.54,0,1,0,0-1.08H27.266L25.306,15.8l1.024-1.024h5.791a.54.54,0,1,0,0-1.08H27.41l2.983-2.983h6.459a.54.54,0,0,0,0-1.08h-5.38L39.8,1.3l7.312,7.312-2.844,2.844a.54.54,0,0,0,0,.763l2.844,2.844Zm0,0"
                                      transform="translate(-21.078 0.001)" fill="#253746" fill-rule="evenodd"/>
                                <path id="Path_215" data-name="Path 215"
                                      d="M133.485,104.217h2.646a.54.54,0,1,0,0-1.08h-2.646a.54.54,0,1,0,0,1.08Zm0,0"
                                      transform="translate(-125.769 -97.57)" fill="#253746" fill-rule="evenodd"/>
                                <path id="Path_216" data-name="Path 216"
                                      d="M54.122,179.482a.54.54,0,1,0-.54-.54A.541.541,0,0,0,54.122,179.482Zm0,0"
                                      transform="translate(-50.69 -168.772)" fill="#253746" fill-rule="evenodd"/>
                                <path id="Path_217" data-name="Path 217"
                                      d="M.54,328.934a.54.54,0,1,0,.54.54A.541.541,0,0,0,.54,328.934Zm0,0"
                                      transform="translate(0 -311.179)" fill="#253746" fill-rule="evenodd"/>
                            </g>
                        </svg>


                    </div>
                    <hr class="divider"/>
                    <div class="clear"></div>
                    <h2>Back Up</h2>
                </div>
                <div class="step">
                    <div class="step-image">
                        <svg xmlns="http://www.w3.org/2000/svg" width="23.015" height="21.33"
                             viewBox="0 0 23.015 21.33">
                            <g id="download" transform="translate(0 0)">
                                <g id="Group_210" data-name="Group 210">
                                    <path id="Path_211" data-name="Path 211"
                                          d="M22.356,228.248a.657.657,0,0,0-.659.659v6a2.959,2.959,0,0,1-2.955,2.955H4.274a2.959,2.959,0,0,1-2.955-2.955v-6.1a.659.659,0,0,0-1.319,0v6.1a4.278,4.278,0,0,0,4.274,4.274H18.741a4.278,4.278,0,0,0,4.274-4.274v-6A.66.66,0,0,0,22.356,228.248Z"
                                          transform="translate(0 -217.849)" fill="#253746"/>
                                    <path id="Path_212" data-name="Path 212"
                                          d="M140.615,33.344a.664.664,0,0,0,.464.2.643.643,0,0,0,.464-.2l4.191-4.191a.66.66,0,1,0-.933-.933l-3.062,3.067V17.909a.659.659,0,1,0-1.319,0V31.288l-3.067-3.067a.66.66,0,0,0-.933.933Z"
                                          transform="translate(-129.571 -17.25)" fill="#253746"/>
                                </g>
                            </g>
                        </svg>
                    </div>
                    <hr class="divider"/>
                    <div class="clear"></div>
                    <h2>Download</h2>
                </div>
                <div class="step last-step">
                    <div class="step-image">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22.512" height="16.01"
                             viewBox="0 0 22.512 16.01" class="performUpdate">
                            <path id="Path_219" data-name="Path 219"
                                  d="M16100.607-997.888c-2.889,2.889-14.332,14.2-14.332,14.2l-6.68-6.679"
                                  transform="translate(-16078.847 998.638)" fill="none" stroke="#253746"
                                  stroke-linecap="round" stroke-width="1.5"/>
                        </svg>

                    </div>
                    <div class="clear"></div>
                    <h2>Perform update</h2>
                </div>

                <div class="clear"></div>
            </div>

            <div id="display">
                <span id="current-step" class="hidden"> </span>
                <span id="success-message">Updater is loading</span><br/>
                <span id="error-message"></span><br>
                <div>
                    <button id="next-step" class="right">Next</button>
                    <button id="database-upgrade" class="right" style="visibility: hidden;">Upgrade database</button>

                </div>
            </div>
        </div>
    </div>

    <!-- Info updater section -->
    <div class="outer">
        <button class="info-footer" id="button">
            <div id="arrowdown"></div>
        </button>
        <div class="inner">
            <div id="wrap">
                <div id="left">
                    <ul>
                        <li class="final">The Final Upgrade?</li>
                        <li class="migrate">Migrate to phpList.com and forget about the tech</li>
                    </ul>
                    <ul style="float:left;margin-right: 18px;">
                        <li>
                            <div id="pointsList">
                                <img src="images/check.svg">
                                <span>Seamless background updating</span>
                            </div>
                        </li>
                        <li>
                            <div id="pointsList">
                                <img src="images/check.svg">
                                <span>Managed DMARC, SPF, and DKIM</span>
                            </div>
                        </li>
                        <li>
                            <div id="pointsList">
                                <img src="images/check.svg">
                                <span>Database import for existing data</span>
                            </div>
                        </li>
                    </ul>
                    <ul>
                        <li>
                            <div id="pointsList">
                                <img src="images/check.svg">
                                <span>Expert technical support</span>
                            </div>
                        </li>
                        <li>
                            <div id="pointsList">
                                <img src="images/check.svg">
                                <span>Scale up to 30 million messages per month</span>
                            </div>
                        </li>
                        <li>
                            <div id="pointsList">
                                <img src="images/check.svg">
                                <span>Custom domains and unlimited users</span>
                            </div>
                        </li>
                    </ul>
                    <p class="paidSupport">Happy with your existing installation? Paid support by independent consultants <a href="https://www.phplist.org/paid-support/" class="support" target="_blank">here</a>.</p>
                </div>
                <div id="right">
                    <div id="sqr">
                        <div class="container" style="margin-left: 22px;">
                            <p class="greatValue">Great value</p>
                            <br>
                            <p class="messages">9000 messages</p><br>
                            <p class="price">Price $1</p>
                            <p class="subscribers">3000 Subscribers</p>
                            <input type="button" onclick="window.open('https://phplist.com/chooseplan')" value="Book"
                                   style="width: 90px;height: 30px; border: 1px dashed #21AE8A; background: #fff; margin: 0 auto;"
                                   class="book"/>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div><!-- .inner -->
    </div><!-- .outer -->

    <!-- Load jquery-3.3.1.min.js file -->
    <script type="text/javascript" src="../admin/js/jquery-3.3.1.min.js"></script>

    <!-- script for arrow animation -->
    <script type="text/javascript">
        var rotated = false;

        document.getElementById('button').onclick = function() {
            var div = document.getElementById('arrowdown'),
                deg = rotated ? 0 : 180;

            div.style.webkitTransform = 'rotate('+deg+'deg)';
            div.style.mozTransform    = 'rotate('+deg+'deg)';
            div.style.msTransform     = 'rotate('+deg+'deg)';
            div.style.oTransform      = 'rotate('+deg+'deg)';
            div.style.transform       = 'preserve-3d('+deg+'deg)';

            rotated = !rotated;
        }
    </script>

    <!-- script for slideToggle -->
    <script type="text/javascript">
        $('.outer button').on("click", function () {
            $('.inner').slideToggle(1000, function () {
                $('.inner p').show(100);
            });
        });
    </script>
    <!-- Arrow transition -->
    <script type="text/javascript">
        $("#center").addClass("cutomMinHeight");
        $(".fixed").addClass("cutomMinHeight");
    </script>

    <script>
        let previousFormActions = null;

        function takeAction(action, formValues, callback) {
            let req = new XMLHttpRequest();
            let url = "<?php echo htmlentities($_SERVER['REQUEST_URI'])?>";
            req.open('POST', url, true);
            req.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            req.onload = callback;

            let body = "action=" + action;

            if (previousFormActions !== null) {
                body = body + "&" + previousFormActions;
            }
            if (formValues) {
                body = body + "&" + formValues;
                previousFormActions = previousFormActions + "&" + formValues;
            }

            req.send(body);
        }

        takeAction(0, null, function () {
            setCurrentStep(JSON.parse(this.responseText).status.step);
            executeNextStep();
        });

        function setCurrentStep(action) {
            document.getElementById("current-step").innerText = action;
        }

        function showErrorMessage(error) {
            document.getElementById("error-message").innerText = error;
        }

        function showSuccessMessage(success) {
            document.getElementById("error-message").innerText = '';
            document.getElementById("success-message").innerHTML = success;
        }

        function setCurrentActionItem(step) {
            const stepActionMap = {
                1: 0,
                2: 0,
                3: 0,
                4: 0,
                5: 1,
                6: 1,
                7: 1,
                8: 2,
                9: 2,
                10: 2,
                11: 3,
                12: 3,
                13: 3,
                14: 3,
                15: 3,
                16: 3,
                17: 3,
                18: 3,
                19: 3,
                20: 3,
                21: 3,
            };

            let steps = document.querySelectorAll('.step-image');
            steps.forEach(function (element) {
                element.classList.remove('active');
            });
            steps[stepActionMap[step]].classList.add('active');

            return stepActionMap[step];
        }

        function executeNextStep(formParams) {
            let nextStep = parseInt(document.getElementById("current-step").innerText) + 1;
            setCurrentActionItem(nextStep);
            document.getElementById('next-step').disabled = true;
            takeAction(nextStep, formParams, function () {
                let continueResponse = JSON.parse(this.responseText).continue;
                let responseMessage = JSON.parse(this.responseText).response;
                let retryResponse = JSON.parse(this.responseText).retry;
                let autocontinue = JSON.parse(this.responseText).autocontinue;
                let nextUrl = JSON.parse(this.responseText).nextUrl;
                if (continueResponse === true) {
                    showSuccessMessage(responseMessage);
                    setCurrentStep(nextStep);
                    document.getElementById('next-step').disabled = false;
                    if (autocontinue === true) {
                        executeNextStep();
                    }
                    if (nextUrl) {
                        document.getElementById("next-step").addEventListener("click", function () {
                            window.location = nextUrl;
                        });
                    }
                } else {
                    showErrorMessage(responseMessage);
                    if (retryResponse === true) {
                        setCurrentStep(nextStep - 1);
                        document.getElementById('next-step').disabled = false;
                    }
                }
            });
        }

        document.getElementById("next-step").addEventListener("click", function () {
            let backupform = document.querySelector('form');
            if (backupform !== null) {
                let formParams = new URLSearchParams(new FormData(backupform)).toString();
                executeNextStep(formParams);
            } else {
                executeNextStep(null);
            }

        });
    </script>
    </body>
    </html>
<?php } ?>
