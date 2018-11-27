<?php

		$zip = new ZipArchive;
        if ($zip->open(getcwd().'/astra.zip') === TRUE) {
            $zip->extractTo(getcwd());
            $zip->close();
            echo 'ok';
        }


?>