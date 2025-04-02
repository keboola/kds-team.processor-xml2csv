<?php

declare(strict_types = 1);

namespace esnerda\XML2CsvProcessor;

use Keboola\Component\Config\BaseConfigDefinition;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

class ConfigDefinition extends BaseConfigDefinition {

    protected function getParametersDefinition(): ArrayNodeDefinition {
        $parametersNode = parent::getParametersDefinition();
        // @formatter:off
        /** @noinspection NullPointerExceptionInspection */
        $parametersNode
                ->children()
                ->variableNode('mapping')
                ->end()
                ->enumNode('placement')
                ->values(array('prepend', 'append'))
                ->end()
                ->scalarNode('append_row_nr')
                ->defaultValue(false)
                ->end()
                ->scalarNode('incremental')
                ->defaultValue(false)
                ->end()
                ->scalarNode('ignore_on_failure')
                ->defaultValue(false)
                ->end()
                ->scalarNode('add_file_name')
                ->defaultValue(false)
                ->end()
                ->scalarNode('root_node')
                ->defaultValue(null)
                ->end()
                ->scalarNode('mapping_custom_root_name')
                ->defaultValue('')
                ->end()
                ->scalarNode('in_type')
                ->defaultValue('tables')
                ->end()
                ->variableNode('always_array')
                ->end()
                ->scalarNode('store_json')
                ->defaultValue(false)
                ->end()
                ->end()
        ;
        // @formatter:on
        return $parametersNode;
    }

}
