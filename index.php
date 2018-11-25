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
     * @throws UpdateException
     */
    public function getCurrentVersion()
    {
        $version = file_get_contents('../admin/init.php');
        $matches = array();
        preg_match_all('/define\(\"VERSION\",\"(.*)\"\);/', $version,$matches);

        if(isset($matches[1][0])) {
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
            'updater'=>1,
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
     * Get a PDO connection
     * @return PDO
     */
    function getConnection()
    {

        require __DIR__ . '/../config/config.php';

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
            'index.php',
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
    function deleteTemporaryFiles() {
        $isTempDirDeleted = $this->rmdir_recursive(self::DOWNLOAD_PATH);
        if($isTempDirDeleted===false){
            throw new \UpdateException("Could not delete temporary files!");
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




    //  $update->temp_dir();
    // $update->deleteFiles();
    // $update->downloadUpdate();
    //$update->moveNewFiles();
    //$update->replacePHPEntryPoints();
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
 *
 *
 */
if(isset($_POST['action'])) {
    set_time_limit(0);

    //ensure that $action is integer

    $action = (int)$_POST['action'];

    header('Content-Type: application/json');
    switch ($action) {
        case 0:
            $statusJson= $update->currentUpdateStep();
            echo json_encode($statusJson);
            break;
        case 1:
            $currentVersion = $update->getCurrentVersion();
            $updateMessage= $update->checkIfThereIsAnUpdate();
            $isThereAnUpdate = $update->availableUpdate();
            if($isThereAnUpdate===false){
                echo(json_encode(array('continue' => false, 'response' => 'There is no update available.')));
            } else {
                echo(json_encode(array('continue' => true,'response'=>$updateMessage)));
            }

            break;
        case 2:
            $unexpectedFiles = $update->checkRequiredFiles();
            if(count($unexpectedFiles) !== 0) {
                echo(json_encode(array('continue' => false, 'response' => $unexpectedFiles)));
            } else {
                echo(json_encode(array('continue' => true)));
            }
            break;
        case 3:
            $notWriteableFiles = $update->checkWritePermissions();
            if(count($notWriteableFiles) !== 0) {
                echo(json_encode(array('continue' => false, 'response' => $notWriteableFiles)));
            } else {
                echo(json_encode(array('continue' => true)));
            }
            break;
        case 4:
            try {
                $update->downloadUpdate();
                echo(json_encode(array('continue' => true)));
            } catch (\Exception $e) {
                echo(json_encode(array('continue' => false, 'response' => $e->getMessage())));
            }
            break;
        case 5:
            $on = $update->addMaintenanceMode();
            if($on===false){
                echo(json_encode(array('continue' => false, 'response' => 'Cannot set the maintenance mode on!')));
            } else {
                echo(json_encode(array('continue' => true)));
            }
            break;
        case 6:
            try {
                $update->replacePHPEntryPoints();
                echo(json_encode(array('continue' => true)));
            } catch (\Exception $e) {
                echo(json_encode(array('continue' => false, 'response' => $e->getMessage())));
            }
            break;
        case 7:
            try {
                $update->deleteFiles();
                echo(json_encode(array('continue' => true)));
            } catch (\Exception $e) {
                echo(json_encode(array('continue' => false, 'response' => $e->getMessage())));
            }
            break;
        case 8:
            try {
                $update->moveNewFiles();
                echo(json_encode(array('continue' => true)));
            } catch (\Exception $e) {
                echo(json_encode(array('continue' => false, 'response' => $e->getMessage())));
            }
            break;
        case 9:
            try {
                $update->moveEntryPHPpoints();
                echo(json_encode(array('continue' => true)));
            } catch (\Exception $e) {
                echo(json_encode(array('continue' => false, 'response' => $e->getMessage())));
            }
            break;
        case 10:
            try {
                $update->deleteTemporaryFiles();
                echo(json_encode(array('continue' => true)));
            } catch (\Exception $e) {
                echo(json_encode(array('continue' => false, 'response' => $e->getMessage())));
            }
            break;

        case 11:

            try {
                $update->removeMaintenanceMode();
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
            /** PHPList CSS **/
            body {
                background-color: #eeeeee45;
                font-family: 'Source Sans Pro', sans-serif;
                margin-top: 50px;
            }
            button {
                background-color: #F29D71;
                color: white;
                border-radius: 55px;
                height: 40px;
                padding-left: 30px;
                padding-right: 30px;
                font-size: 15px;
                text-transform: uppercase;
                margin-top: 20px;
                border: none;
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
            #display {
                background-color: white;
                padding-left: 20px;
                padding-top: 20px;
                padding-bottom: 20px;
                border-radius: 20px;
            }
            #logo {
                color: #8C8C8C;
                font-size: 20px;
                text-align: center;
                padding-bottom: 50px;
            }
            #logo img {
                margin-bottom: 20px;
            }
            #steps h2 {
                font-size: 15px;
                color: #8C8C8C;
                width: 50px;
                text-align: center;
                padding-left: 10px;
            }
            #steps {
                width: 80%;
                margin: auto;
                padding-bottom: 30px;
            }
            #first-step {
                width: calc((25% - 70px)/2) !important;
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
                border: 1px solid #8C8C8C;
                margin-bottom: 20px;
                float: left;
            }
            .step-image svg {
                width: 50%;
                padding-top: 25%;
                padding-left: 25%;
            }
            .active {
                background-color: lightblue;
            }
            .active svg path {
                fill: white;
            }
            .clear {
                clear: both;
            }
            .divider {
                border: 0.3px solid #8C8C8C;
                width: inherit;
                margin-top: 30px;
            }
        </style>
    </head>
    <body>
<<<<<<< HEAD

    <div id="center">
        <div id="logo">
            <svg width="47.055mm" height="14.361mm" version="1.1" viewBox="0 0 166.73201 50.884" xmlns="http://www.w3.org/2000/svg" >
                <g transform="translate(-199.83 -209.59)" fill="#8C8C8C">
                    <path transform="matrix(.9375 0 0 .9375 199.83 209.59)" d="m27.139 0a27.138 27.138 0 0 0 -22.072 11.385l17.771 17.951 6.543-6.5176-3.7148-3.7109-0.064454-0.083984c-0.83947-1.1541-1.2461-2.4403-1.2461-3.9336 0-3.7963 3.0896-6.8848 6.8848-6.8848s6.8828 3.0885 6.8828 6.8848c0 1.6395-0.55599 3.1158-1.6504 4.3926l-0.070312 0.076172-3.2715 3.2617 17.648 17.611a27.138 27.138 0 0 0 3.4961 -13.293 27.138 27.138 0 0 0 -27.137 -27.139zm4.1035 10.855c-2.3371 0-4.2383 1.9003-4.2383 4.2363 0.001067 0.89067 0.21941 1.6238 0.68555 2.2969l3.5684 3.5625 3.2383-3.2285c0.66027-0.784 0.98047-1.6442 0.98047-2.6309 0-2.336-1.8973-4.2363-4.2344-4.2363zm-27.658 2.8438a27.138 27.138 0 0 0 -3.584 13.439 27.138 27.138 0 0 0 27.139 27.137 27.138 27.138 0 0 0 22.23 -11.594l-18.113-17.992-6.5527 6.5273 3.5117 3.5547c0.94187 1.232 1.4395 2.6647 1.4395 4.1484-0.001067 3.7952-3.0896 6.8848-6.8848 6.8848-3.7963 0-6.8848-3.0885-6.8848-6.8848 0-1.2864 0.34507-2.5299 1-3.5977l0.082031-0.13477 3.998-3.9824-17.381-17.506zm19.248 19.385l-3.7637 3.748c-0.35093 0.62293-0.53516 1.3402-0.53516 2.0879 0 2.3339 1.9003 4.2363 4.2363 4.2363s4.2402-1.9003 4.2402-4.2363c0-0.88533-0.28766-1.7151-0.84766-2.4746l-3.3301-3.3613z" stroke-width="1.0667"/>
                    <path d="m263.24 229.86c1.53-1.693 2.958-2.438 4.997-2.438 5.236 0 7.921 4.556 7.921 9.043s-2.754 9.315-7.921 9.281c-2.144 0-3.671-0.714-4.997-2.176v7.955h-3.06v-23.627h3.06zm4.997 13.132c2.992 0 4.726-3.06 4.726-6.459 0-3.332-1.698-6.323-4.726-6.323-6.969 0-6.969 12.782 0 12.782z"/>
                    <path d="m282.11 229.86c1.122-2.057 2.89-2.71 4.896-2.71 4.861 0 6.527 3.468 6.527 7.445v10.403h-3.06v-10.403c0-2.55-0.852-4.691-3.47-4.691-2.992 0-4.896 1.802-4.896 4.691v10.403h-3.062v-24.546h3.062z"/>
                    <path d="m300.24 229.86c1.527-1.693 2.957-2.438 4.997-2.438 5.233 0 7.922 4.556 7.922 9.043s-2.754 9.315-7.922 9.281c-2.144 0-3.672-0.714-4.997-2.176v7.955h-3.062v-23.627h3.062zm4.997 13.132c2.99 0 4.726-3.06 4.726-6.459 0-3.332-1.7-6.323-4.726-6.323-6.969 0-6.969 12.782 0 12.782z"/>
                    <path d="m316.81 245v-24.546h3.229v21.622h12.341v2.924z"/>
                    <path d="m334.68 223.88v-3.434h3.4v3.434zm0.17 21.112v-17.372h3.061v17.372z"/>
                    <path d="m340.85 239.01h3.195c0.17 2.584 1.77 3.773 3.738 3.773 2.006 0 3.943-0.918 3.943-2.754 0-0.884-0.512-1.428-1.395-1.835-0.477-0.204-1.02-0.374-1.633-0.545-3.16-0.781-7.785-1.121-7.785-5.371 0-3.808 3.469-4.861 6.562-4.861 3.363 0 6.936 1.462 6.936 6.392h-3.229c0-2.958-1.904-3.672-3.705-3.672-1.734 0-3.332 0.646-3.332 2.142 0 0.714 0.439 1.156 1.395 1.53 0.477 0.204 1.055 0.374 1.664 0.51 0.613 0.136 1.293 0.306 1.975 0.441 2.686 0.646 5.812 1.666 5.812 5.27 0 3.944-3.807 5.474-7.139 5.474-3.5-1e-3 -6.934-2.074-7.002-6.494z"/>
                    <path d="m359.39 240.17v-9.689h-3.398v-2.55h3.398v-4.521h3.062v4.521h4.113v2.55h-4.113v9.689c0 1.224-0.035 2.753 1.562 2.753 0.309 0 0.613-0.067 0.953-0.102 0.34-0.068 0.682-0.136 1.6-0.238v2.516c-1.09 0.272-1.805 0.408-2.584 0.408-4.253 0-4.593-2.21-4.593-5.337z"/>
                </g>
            </svg>
            <h1>Updating phpList to the latest version</h1>
        </div>
        <div id="steps">
            <div id="first-step"> </div>
            <div class="step">
                <div class="step-image active">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 23.015 21.33">
                        <defs>
                            <style>
                                .path {
                                    fill: #8a9798;
                                }
                            </style>
                        </defs>
                        <g id="download" transform="translate(0 -17.25)">
                            <g class="path" transform="translate(0 17.25)">
                                <path class="cls-1" d="M22.356,228.248a.657.657,0,0,0-.659.659v6a2.959,2.959,0,0,1-2.955,2.955H4.274a2.959,2.959,0,0,1-2.955-2.955v-6.1a.659.659,0,0,0-1.319,0v6.1a4.278,4.278,0,0,0,4.274,4.274H18.741a4.278,4.278,0,0,0,4.274-4.274v-6A.66.66,0,0,0,22.356,228.248Z" transform="translate(0 -217.849)"/>
                                <path class="path" d="M140.615,33.344a.664.664,0,0,0,.464.2.643.643,0,0,0,.464-.2l4.191-4.191a.66.66,0,1,0-.933-.933l-3.062,3.067V17.909a.659.659,0,1,0-1.319,0V31.288l-3.067-3.067a.66.66,0,0,0-.933.933Z" transform="translate(-129.571 -17.25)"/>
                            </g>
                        </g>
                    </svg>
                </div>
                <hr class="divider" />
                <div class="clear"></div>
                <h2>Initialize</h2>
            </div>
            <div class="step">
                <div class="step-image ">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 23.015 21.33">
                        <defs>
                            <style>
                                .path {
                                    fill: #8a9798;
                                }
                            </style>
                        </defs>
                        <g id="asdf" transform="translate(0 -17.25)">
                            <g class="path" transform="translate(0 17.25)">
                                <path class="cls-1" d="M22.356,228.248a.657.657,0,0,0-.659.659v6a2.959,2.959,0,0,1-2.955,2.955H4.274a2.959,2.959,0,0,1-2.955-2.955v-6.1a.659.659,0,0,0-1.319,0v6.1a4.278,4.278,0,0,0,4.274,4.274H18.741a4.278,4.278,0,0,0,4.274-4.274v-6A.66.66,0,0,0,22.356,228.248Z" transform="translate(0 -217.849)"/>
                                <path class="path" d="M140.615,33.344a.664.664,0,0,0,.464.2.643.643,0,0,0,.464-.2l4.191-4.191a.66.66,0,1,0-.933-.933l-3.062,3.067V17.909a.659.659,0,1,0-1.319,0V31.288l-3.067-3.067a.66.66,0,0,0-.933.933Z" transform="translate(-129.571 -17.25)"/>
                            </g>
                        </g>
                    </svg>
                </div>
                <hr class="divider" />
                <div class="clear"></div>
                <h2>Download</h2>
            </div>
            <div class="step">
                <div class="step-image">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 23.015 21.33">
                        <defs>
                            <style>
                                .path {
                                    fill: #8a9798;
                                }
                            </style>
                        </defs>
                        <g id="foo" transform="translate(0 -17.25)">
                            <g class="path" transform="translate(0 17.25)">
                                <path class="cls-1" d="M22.356,228.248a.657.657,0,0,0-.659.659v6a2.959,2.959,0,0,1-2.955,2.955H4.274a2.959,2.959,0,0,1-2.955-2.955v-6.1a.659.659,0,0,0-1.319,0v6.1a4.278,4.278,0,0,0,4.274,4.274H18.741a4.278,4.278,0,0,0,4.274-4.274v-6A.66.66,0,0,0,22.356,228.248Z" transform="translate(0 -217.849)"/>
                                <path class="path" d="M140.615,33.344a.664.664,0,0,0,.464.2.643.643,0,0,0,.464-.2l4.191-4.191a.66.66,0,1,0-.933-.933l-3.062,3.067V17.909a.659.659,0,1,0-1.319,0V31.288l-3.067-3.067a.66.66,0,0,0-.933.933Z" transform="translate(-129.571 -17.25)"/>
                            </g>
                        </g>
                    </svg>
                </div>
                <hr class="divider" />
                <div class="clear"></div>
                <h2>Back Up</h2>
            </div>
            <div class="step last-step">
                <div class="step-image">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 23.015 21.33">
                        <defs>
                            <style>
                                .path {
                                    fill: #8a9798;
                                }
                            </style>
                        </defs>
                        <g id="bar" transform="translate(0 -17.25)">
                            <g class="path" transform="translate(0 17.25)">
                                <path class="cls-1" d="M22.356,228.248a.657.657,0,0,0-.659.659v6a2.959,2.959,0,0,1-2.955,2.955H4.274a2.959,2.959,0,0,1-2.955-2.955v-6.1a.659.659,0,0,0-1.319,0v6.1a4.278,4.278,0,0,0,4.274,4.274H18.741a4.278,4.278,0,0,0,4.274-4.274v-6A.66.66,0,0,0,22.356,228.248Z" transform="translate(0 -217.849)"/>
                                <path class="path" d="M140.615,33.344a.664.664,0,0,0,.464.2.643.643,0,0,0,.464-.2l4.191-4.191a.66.66,0,1,0-.933-.933l-3.062,3.067V17.909a.659.659,0,1,0-1.319,0V31.288l-3.067-3.067a.66.66,0,0,0-.933.933Z" transform="translate(-129.571 -17.25)"/>
                            </g>
                        </g>
                    </svg>
                </div>
                <div class="clear"></div>
                <h2>Perform update</h2>
            </div>
            <div class="clear"></div>
        </div>
        <div id="display">
             <span> Current step: <span id="current-step"> </span> <br>
                 <span id="success-message" </span><br>
            <span id="error-message" </span><br>
        </div>
        <button id="next-step" class="right">Next</button>
    </div>


=======

    <div id="center">
        <div id="logo">
            <svg width="47.055mm" height="14.361mm" version="1.1" viewBox="0 0 166.73201 50.884" xmlns="http://www.w3.org/2000/svg" >
                <g transform="translate(-199.83 -209.59)" fill="#8C8C8C">
                    <path transform="matrix(.9375 0 0 .9375 199.83 209.59)" d="m27.139 0a27.138 27.138 0 0 0 -22.072 11.385l17.771 17.951 6.543-6.5176-3.7148-3.7109-0.064454-0.083984c-0.83947-1.1541-1.2461-2.4403-1.2461-3.9336 0-3.7963 3.0896-6.8848 6.8848-6.8848s6.8828 3.0885 6.8828 6.8848c0 1.6395-0.55599 3.1158-1.6504 4.3926l-0.070312 0.076172-3.2715 3.2617 17.648 17.611a27.138 27.138 0 0 0 3.4961 -13.293 27.138 27.138 0 0 0 -27.137 -27.139zm4.1035 10.855c-2.3371 0-4.2383 1.9003-4.2383 4.2363 0.001067 0.89067 0.21941 1.6238 0.68555 2.2969l3.5684 3.5625 3.2383-3.2285c0.66027-0.784 0.98047-1.6442 0.98047-2.6309 0-2.336-1.8973-4.2363-4.2344-4.2363zm-27.658 2.8438a27.138 27.138 0 0 0 -3.584 13.439 27.138 27.138 0 0 0 27.139 27.137 27.138 27.138 0 0 0 22.23 -11.594l-18.113-17.992-6.5527 6.5273 3.5117 3.5547c0.94187 1.232 1.4395 2.6647 1.4395 4.1484-0.001067 3.7952-3.0896 6.8848-6.8848 6.8848-3.7963 0-6.8848-3.0885-6.8848-6.8848 0-1.2864 0.34507-2.5299 1-3.5977l0.082031-0.13477 3.998-3.9824-17.381-17.506zm19.248 19.385l-3.7637 3.748c-0.35093 0.62293-0.53516 1.3402-0.53516 2.0879 0 2.3339 1.9003 4.2363 4.2363 4.2363s4.2402-1.9003 4.2402-4.2363c0-0.88533-0.28766-1.7151-0.84766-2.4746l-3.3301-3.3613z" stroke-width="1.0667"/>
                    <path d="m263.24 229.86c1.53-1.693 2.958-2.438 4.997-2.438 5.236 0 7.921 4.556 7.921 9.043s-2.754 9.315-7.921 9.281c-2.144 0-3.671-0.714-4.997-2.176v7.955h-3.06v-23.627h3.06zm4.997 13.132c2.992 0 4.726-3.06 4.726-6.459 0-3.332-1.698-6.323-4.726-6.323-6.969 0-6.969 12.782 0 12.782z"/>
                    <path d="m282.11 229.86c1.122-2.057 2.89-2.71 4.896-2.71 4.861 0 6.527 3.468 6.527 7.445v10.403h-3.06v-10.403c0-2.55-0.852-4.691-3.47-4.691-2.992 0-4.896 1.802-4.896 4.691v10.403h-3.062v-24.546h3.062z"/>
                    <path d="m300.24 229.86c1.527-1.693 2.957-2.438 4.997-2.438 5.233 0 7.922 4.556 7.922 9.043s-2.754 9.315-7.922 9.281c-2.144 0-3.672-0.714-4.997-2.176v7.955h-3.062v-23.627h3.062zm4.997 13.132c2.99 0 4.726-3.06 4.726-6.459 0-3.332-1.7-6.323-4.726-6.323-6.969 0-6.969 12.782 0 12.782z"/>
                    <path d="m316.81 245v-24.546h3.229v21.622h12.341v2.924z"/>
                    <path d="m334.68 223.88v-3.434h3.4v3.434zm0.17 21.112v-17.372h3.061v17.372z"/>
                    <path d="m340.85 239.01h3.195c0.17 2.584 1.77 3.773 3.738 3.773 2.006 0 3.943-0.918 3.943-2.754 0-0.884-0.512-1.428-1.395-1.835-0.477-0.204-1.02-0.374-1.633-0.545-3.16-0.781-7.785-1.121-7.785-5.371 0-3.808 3.469-4.861 6.562-4.861 3.363 0 6.936 1.462 6.936 6.392h-3.229c0-2.958-1.904-3.672-3.705-3.672-1.734 0-3.332 0.646-3.332 2.142 0 0.714 0.439 1.156 1.395 1.53 0.477 0.204 1.055 0.374 1.664 0.51 0.613 0.136 1.293 0.306 1.975 0.441 2.686 0.646 5.812 1.666 5.812 5.27 0 3.944-3.807 5.474-7.139 5.474-3.5-1e-3 -6.934-2.074-7.002-6.494z"/>
                    <path d="m359.39 240.17v-9.689h-3.398v-2.55h3.398v-4.521h3.062v4.521h4.113v2.55h-4.113v9.689c0 1.224-0.035 2.753 1.562 2.753 0.309 0 0.613-0.067 0.953-0.102 0.34-0.068 0.682-0.136 1.6-0.238v2.516c-1.09 0.272-1.805 0.408-2.584 0.408-4.253 0-4.593-2.21-4.593-5.337z"/>
                </g>
            </svg>
            <h1>Updating phpList to the latest version</h1>
        </div>
        <div id="steps">
            <div id="first-step"></div>
            <div class="step">
                <div class="step-image active">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 23.015 21.33">
                        <defs>
                            <style>
                                .path {
                                    fill: #8a9798;
                                }
                            </style>
                        </defs>
                        <g id="download" transform="translate(0 -17.25)">
                            <g class="path" transform="translate(0 17.25)">
                                <path class="cls-1" d="M22.356,228.248a.657.657,0,0,0-.659.659v6a2.959,2.959,0,0,1-2.955,2.955H4.274a2.959,2.959,0,0,1-2.955-2.955v-6.1a.659.659,0,0,0-1.319,0v6.1a4.278,4.278,0,0,0,4.274,4.274H18.741a4.278,4.278,0,0,0,4.274-4.274v-6A.66.66,0,0,0,22.356,228.248Z" transform="translate(0 -217.849)"/>
                                <path class="path" d="M140.615,33.344a.664.664,0,0,0,.464.2.643.643,0,0,0,.464-.2l4.191-4.191a.66.66,0,1,0-.933-.933l-3.062,3.067V17.909a.659.659,0,1,0-1.319,0V31.288l-3.067-3.067a.66.66,0,0,0-.933.933Z" transform="translate(-129.571 -17.25)"/>
                            </g>
                        </g>
                    </svg>
                </div>
                <hr class="divider" />
                <div class="clear"></div>
                <h2>Initialize</h2>
            </div>
            <div class="step">
                <div class="step-image ">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 23.015 21.33">
                        <defs>
                            <style>
                                .path {
                                    fill: #8a9798;
                                }
                            </style>
                        </defs>
                        <g id="asdf" transform="translate(0 -17.25)">
                            <g class="path" transform="translate(0 17.25)">
                                <path class="cls-1" d="M22.356,228.248a.657.657,0,0,0-.659.659v6a2.959,2.959,0,0,1-2.955,2.955H4.274a2.959,2.959,0,0,1-2.955-2.955v-6.1a.659.659,0,0,0-1.319,0v6.1a4.278,4.278,0,0,0,4.274,4.274H18.741a4.278,4.278,0,0,0,4.274-4.274v-6A.66.66,0,0,0,22.356,228.248Z" transform="translate(0 -217.849)"/>
                                <path class="path" d="M140.615,33.344a.664.664,0,0,0,.464.2.643.643,0,0,0,.464-.2l4.191-4.191a.66.66,0,1,0-.933-.933l-3.062,3.067V17.909a.659.659,0,1,0-1.319,0V31.288l-3.067-3.067a.66.66,0,0,0-.933.933Z" transform="translate(-129.571 -17.25)"/>
                            </g>
                        </g>
                    </svg>
                </div>
                <hr class="divider" />
                <div class="clear"></div>
                <h2>Download</h2>
            </div>
            <div class="step">
                <div class="step-image">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 23.015 21.33">
                        <defs>
                            <style>
                                .path {
                                    fill: #8a9798;
                                }
                            </style>
                        </defs>
                        <g id="foo" transform="translate(0 -17.25)">
                            <g class="path" transform="translate(0 17.25)">
                                <path class="cls-1" d="M22.356,228.248a.657.657,0,0,0-.659.659v6a2.959,2.959,0,0,1-2.955,2.955H4.274a2.959,2.959,0,0,1-2.955-2.955v-6.1a.659.659,0,0,0-1.319,0v6.1a4.278,4.278,0,0,0,4.274,4.274H18.741a4.278,4.278,0,0,0,4.274-4.274v-6A.66.66,0,0,0,22.356,228.248Z" transform="translate(0 -217.849)"/>
                                <path class="path" d="M140.615,33.344a.664.664,0,0,0,.464.2.643.643,0,0,0,.464-.2l4.191-4.191a.66.66,0,1,0-.933-.933l-3.062,3.067V17.909a.659.659,0,1,0-1.319,0V31.288l-3.067-3.067a.66.66,0,0,0-.933.933Z" transform="translate(-129.571 -17.25)"/>
                            </g>
                        </g>
                    </svg>
                </div>
                <hr class="divider" />
                <div class="clear"></div>
                <h2>Back Up</h2>
            </div>
            <div class="step last-step">
                <div class="step-image">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 23.015 21.33">
                        <defs>
                            <style>
                                .path {
                                    fill: #8a9798;
                                }
                            </style>
                        </defs>
                        <g id="bar" transform="translate(0 -17.25)">
                            <g class="path" transform="translate(0 17.25)">
                                <path class="cls-1" d="M22.356,228.248a.657.657,0,0,0-.659.659v6a2.959,2.959,0,0,1-2.955,2.955H4.274a2.959,2.959,0,0,1-2.955-2.955v-6.1a.659.659,0,0,0-1.319,0v6.1a4.278,4.278,0,0,0,4.274,4.274H18.741a4.278,4.278,0,0,0,4.274-4.274v-6A.66.66,0,0,0,22.356,228.248Z" transform="translate(0 -217.849)"/>
                                <path class="path" d="M140.615,33.344a.664.664,0,0,0,.464.2.643.643,0,0,0,.464-.2l4.191-4.191a.66.66,0,1,0-.933-.933l-3.062,3.067V17.909a.659.659,0,1,0-1.319,0V31.288l-3.067-3.067a.66.66,0,0,0-.933.933Z" transform="translate(-129.571 -17.25)"/>
                            </g>
                        </g>
                    </svg>
                </div>
                <div class="clear"></div>
                <h2>Perform update</h2>
            </div>
            <div class="clear"></div>
        </div>
        <div id="display">
            Initializing<br/>
            Current version is: <br/>
            Update to phpList  available.<br/>
        </div>
        <button class="right">Go to dashboard</button>
    </div>
    <span> Current step: <span id="current-step"> </span> <br>
    <span id="error-message" </span><br>
    <button id="next-step"> Next</button>
>>>>>>> 63a4e041e0bbe844a784d9a64731ddc7a1f39039
    </body>

    <script>

        function takeAction(action, callback) {
            let req = new XMLHttpRequest();
            let url = "<?php echo htmlentities( $_SERVER['REQUEST_URI'] )?>";
            req.open('POST', url, true);
            req.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            req.onload  = callback;
            req.send("action="+action);

        }

        takeAction(0,function () {
            setCurrentStep(JSON.parse(this.responseText).step);
        });

        function setCurrentStep(action){
            document.getElementById("current-step").innerText=action;
        }
        function showErrorMessage(error){
            document.getElementById("error-message").innerText=error;
        }
        function showSuccessMessage(success){
            document.getElementById("success-message").innerText=success;
        }

        document.getElementById("next-step").addEventListener("click",function () {
            let nextStep = parseInt(document.getElementById("current-step").innerText)+1;
            document.getElementById('next-step').disabled = true;
            takeAction(nextStep, function () {
                let continueResponse = JSON.parse(this.responseText).continue;
                let responseMessage = JSON.parse(this.responseText).response;
                if (continueResponse===true) {
                    showSuccessMessage(responseMessage);
                    setCurrentStep(nextStep);
                    document.getElementById('next-step').disabled = false;
                } else { showErrorMessage("Failed!");

                }
            });
        });

    </script>

    </html>
<?php } ?>