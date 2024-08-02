<?php

declare(strict_types = 1);

namespace esnerda\XML2CsvProcessor;

use Keboola\Component\BaseComponent;

class Component extends BaseComponent {

    protected function run(): void {
        if ($this->getConfig()->getMappingRootName() != '') {
            $mappinig_root_name = $this->getConfig()->getMappingRootName();
        } else {
            $mappinig_root_name = 'root';
        }

        if ($this->getConfig()->getRootNode() != NULL && $this->getConfig()->getMappingRootName() == '') {
            $nodes = explode('.', $this->getConfig()->getRootNode());
            $mappinig_root_name = $nodes[count($nodes) - 1];
        }
        $jsonParser = new JsonToCSvParser($this->getConfig()->getMapping(), $this->getLogger(), $mappinig_root_name);

        $processor = new Processor(
                $jsonParser, $this->getConfig()->getAppendRowNr(),
                $this->getConfig()->getForceArrayAttributes(),
                $this->getConfig()->isIncremental(),
                $this->getConfig()->getRootNode(),
                $this->getConfig()->addFileName(),
                $this->getConfig()->ignoreOnFailure(),
                $this->getLogger(),
                $this->getConfig()->storeJson()
        );



        $processor->parseInput($this->getDataDir(), $this->getConfig()->getInputType());
    }

    protected function getConfigClass(): string {
        return Config::class;
    }

    protected function getConfigDefinitionClass(): string {
        return ConfigDefinition::class;
    }

}
