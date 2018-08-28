<?php
/**
 * Updater for phpList 3
 * @author Xheni Myrtaj
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

    }

}

$update = new updater();
var_dump($update->checkWritePermissions());
var_dump($update->checkRequiredFiles());