<?php

namespace esnerda\XML2CsvProcessor;

use Keboola\Component\UserException;
use Symfony\Component\Finder\Finder;
use esnerda\XML2CsvProcessor\XML2JsonConverter;

class Processor
{
    const FILE_NAME_COL_NAME = 'keboola_file_name_col';
    /** @var  string */
    private $jsonParser;
    private $root_el;

    /** @var  bool */
    private $incremental;
    private $add_row_nr;
    private $forceArrayAttrs;
    private $addFileName;

    /** @var LoggerInterface */
    private $logger;

    public function __construct($jsonParser, bool $add_row_nr, $forceArrayAttrs, bool $incremental, string $root_el, $addFileName, $ignoreOnFailure, $logger)
    {
        $this->jsonParser = $jsonParser;
        $this->add_row_nr = $add_row_nr;
        $this->forceArrayAttrs = $forceArrayAttrs;
        $this->incremental = $incremental;
        $this->root_el = $root_el;
        $this->addFileName = $addFileName;
        $this->ignoreOnFailure = $ignoreOnFailure;
        $this->logger = $logger;
    }

    public function stampNames(string $datadir, string $type): self
    {
        return $this->processFiles(
            sprintf("%s/in/" . $type . '/', $datadir),
            sprintf("%s/out/tables/", $datadir)
        );
    }

    private function processFiles(string $inputDir, string $outputDir): self
    {
        //$this->ensureDir($outputDir, $inputDir);

        $finderFiles = new Finder();

        $finderFiles->files()->in($inputDir)->notName('*.manifest');
        $finderFiles->sortByName();
        $xml_parser = new XML2JsonConverter();
        $manifests = $this->getManifests($inputDir);

        foreach ($finderFiles as $file) {
            $this->logger->info("Parsing file " . $file->getFileName());
            try {
                $xml_string =trim(file_get_contents($file->getRealPath()));
                if (strlen($xml_string)==0) {
                    $this->logger->info("File" .$file->getFileName() . "is empty, skipping");
                    continue;
                }
                $this->logger->info("Converting to JSON..");
                $json_result_txt = $xml_parser->xml2json($xml_string, $this->add_row_nr, $this->forceArrayAttrs, $this->ignoreOnFailure);
                // check for err in case on ignore of failure
                if ($this->ignoreOnFailure && substr($json_result_txt, 0, 3)=='ERR') {
                    $this->logger->warn("Failed to parse file: ".$file->getFileName().' '.$json_result_txt);
                    continue;
                }
                
                // get root if specified
                $json_result_root = $this->getRoot(json_decode($json_result_txt));
                // add file name col
                if ($this->addFileName) {
                    $json_result_root = $this->addFileName(json_encode($json_result_root), $file->getFileName(), self::FILE_NAME_COL_NAME);
                    // convert back to json array
                    $json_result_root =json_decode($json_result_root);
                }
                file_put_contents($outputDir . $file->getFileName() . '.json', json_encode($json_result_root));
                $this->logger->info("Converting to CSV..");
                $this->jsonParser->parse($json_result_root);
            } catch (\Throwable $e) {
                throw new UserException("Failed to parse file: ".$file->getFileName().' '.$e->getMessage(), 1, $e);
            }

           
        }
        $this->logger->info("Writting results..");
        $csv_files = $this->jsonParser->getCsvFiles();

        $this->storeResults($outputDir, $csv_files, $this->incremental);
        return $this;
    }

    private function addFileName($json, $fileName, $colName)
    {
        // convert to arrays
        $json_arr = json_decode($json, true);
        // add filename col to root
        if (!is_array($json_arr)) {
            $json_arr[$colName] = $fileName;
        } else {
            // if its array, add field to all members
            foreach ($json_arr as $key => $entry) {
                $json_arr[$key][$colName] = $fileName;
            }
        }
        return json_encode($json_arr);
    }
    
    private function getRoot($json)
    {
        if ($this->root_el != null) {
            $nodes = explode('.', $this->root_el);
            $root = $json;
            foreach ($nodes as $node) {
                $root = $root->{$node};
            }
            return $root;
        } else {
            return $json;
        }
    }

    private function getManifests($inputDir)
    {
        $finderManifests = new Finder();
        $manifests = [];
        $finderManifests->files()->in($inputDir)->name('*.manifest');
        foreach ($finderManifests->name('*.manifest') as $manifest) {
            $manFile = file_get_contents($manifest->getRealPath());
            $destination = explode('.', json_decode($manFile)->destination);
            if (count($destination) >= 2) {
                $manifests[$manifest->getFilename()] = $destination[0] . '.' . $destination[1];
            }
        }
        return $manifests;
    }

    /**
     * @param Table[] $csvFiles
     * @param string $bucketName
     * @param bool $sapiPrefix whether to prefix the output bucket with "in.c-"
     * @param bool $incremental Set the incremental flag in manifest
     * TODO: revisit this
     */
    private function storeResults(string $outdir, array $csvFiles, $incremental = false, $bucketName = null, $sapiPrefix = true)
    {
        foreach ($csvFiles as $key => $file) {
            $path = $outdir;

            if ($file == null) {
                $this->logger->info("No results parsed.");
                return $this;
            }
            if (!is_null($bucketName)) {
                $path .= $bucketName . '/';
                $bucketName = $sapiPrefix ? 'in.c-' . $bucketName : $bucketName;
            }
            if (!is_dir($path)) {
                mkdir($path, null, true);
                chown($path, fileowner($outdir));
                chgrp($path, filegroup($outdir));
            }

            $resFileName = $key . '.csv';
            $manifest = [];
            if (!is_null($bucketName)) {
                $manifest['destination'] = "{$bucketName}.{$key}";
            }
            $manifest['incremental'] = is_null($file->getIncremental()) ? $incremental : $file->getIncremental();
            if (!empty($file->getPrimaryKey())) {
                $manifest['primary_key'] = $file->getPrimaryKey(true);
            }
            $this->logger->info("Writting reult file: " . $resFileName);
            file_put_contents($path . $resFileName . '.manifest', json_encode($manifest));
            copy($file->getPathname(), $path . $resFileName);
        }
    }
}
