<?php
/**
 * Updater for phpList 3
 * @author Xheni Myrtaj <xheni@phplist.com>
 *
 */
class updater
{


    function checkIfThereIsAnUpdate()
    {

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
            '.'=>1,
            '..'=>1,
            'admin'=>1,
            'config'=>1,
            'images'=>1,
            'js'=>1,
            'styles'=>1,
            'texts'=>1,
            '.htaccess'=>1,
            'dl.php'=>1,
            'index.html'=>1,
            'index.php'=>1,
            'lt.php'=>1,
            'ut.php'=>1,
        );

        $existingFiles = scandir(__DIR__.'/../../');

        foreach ($existingFiles as $fileName) {

            if (isset($expectedFiles[$fileName])){
                unset($expectedFiles[$fileName]);
            } else {
                $expectedFiles[$fileName]=1;
            }

        }
        return $expectedFiles;


    }
    function downloadUpdate(){
        $url = "http://10.211.55.4/phplisttest";
        $zipFile = "/../../phpList.zip"; // Local Zip File Path
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
 /** @TODO add this as another step */
        $zip = new ZipArchive;
        $extractPath = "../../";
        if($zip->open($zipFile) != "true"){
            echo "Error :- Unable to open the Zip File";
        }
        /* Extract Zip File */
        $zip->extractTo($extractPath);
        $zip->close();
    }
}

$update = new updater();
var_dump($update->checkWritePermissions());
var_dump($update->checkRequiredFiles());
