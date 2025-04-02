<?php

declare(strict_types = 1);

namespace esnerda\XML2CsvProcessor;

use Keboola\Component\Config\BaseConfig;

class Config extends BaseConfig {

    // @todo implement your custom getters
    public function getMapping(): array {
        return $this->getValue(['parameters', 'mapping']);
    }

    public function getAppendRowNr(): bool {
        return $this->getValue(['parameters', 'append_row_nr']);
    }

    public function isIncremental(): bool {
        return $this->getValue(['parameters', 'incremental']);
    }

    public function ignoreOnFailure(): bool {
        return $this->getValue(['parameters', 'ignore_on_failure']);
    }

    public function getRootNode(): string {
        return $this->getValue(['parameters', 'root_node']);
    }

    public function getInputType(): string {
        return $this->getValue(['parameters', 'in_type']);
    }

    public function getForceArrayAttributes(): array {
        return $this->getValue(['parameters', 'always_array']);
    }

    public function addFileName(): bool {
        return $this->getValue(['parameters', 'add_file_name']);
    }

    public function storeJson(): bool {
        return $this->getValue(['parameters', 'store_json']);
    }

    public function getMappingRootName(): string {
        return $this->getValue(['parameters', 'mapping_custom_root_name']);
    }

}
