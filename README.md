


# XML2CSV processor
Keboola Connection processor for XML to CSV conversion.


Converts XML files to JSON and then to CSV. 

**Credits:**

- For XML2JSON conversion uses modified version of function published in [outlandish.com blogpost](https://outlandish.com/blog/tutorial/xml-to-json/)
- For JSON2CSV conversion uses Keboola developed [Json parser](https://github.com/keboola/php-jsonparser) and [CsvMap](https://github.com/keboola/php-csvmap) for analysis and automatic conversion from JSON to CSV. Supports Generic Ex -like mapping configuration.
# Usage
## Configuration parameters

- **in_type** (enum [`files`,`tables`]) -  specifies the input folder where to look for input data. e.g. when set to `table` the processor will look for inpu in `/in/tables/` folder.
- **incremental** (bool) - flag whether the resulting tables should be uploaded incrementally. Makes most sense with mapping setup, since it allows you to specify primary keys.
- **always_array** (array) - array of tag names that should be always converted to (JSON) array. This is helpful when you know that some of the tags can occur multiple times. For instance `<OrderItem>` tag could probably  have multiple occurrences. If the XML contains only single one it would be treated as an `Object`, including `["OrderItem"]` as a value  of this parameter will ensure it is always an Array. **ATTENTION** it is crutial to set this properly, especially when using no `mapping`! When setup improperly, it may produce unexpected results. See more in [behaviour section](##Behaviour).
- **append_row_nr** (bool) - Use `true` if you want to generate `row_nr` for each object in each Array. This is usefull when you need to setup primary key of child object that has only reference to parent id and not any unique value in parent or global context. Then you would set the PK as [`parent_key`,`row_nr`]
- **root_node** (string) - `.` separated path to the root node of the resulting JSON - usually you only want to map the root array, not all the wrapper tags. For more info see examples below.
- **mapping** (json object) - mapping object in the same format as defined for [generic extractor](https://developers.keboola.com/extend/generic-extractor/map/). For more details on usage see example below.

## Behaviour

### XML attributes
All xml attributes are converted into an object attribute prefixed by `xml_attr_`, while the original value of the tag will be converted to an attribute named `txt_content_`  e.g. 
```xml
<price currency="CZK">100</price>
```
Gets converted to (important for mapping) 
```json
"price": {
		  "xml_attr_currency": "CZK",
		  "txt_content_": "200"
		 }
```

### XML Namespaces
Currently the processor has limited support of xml namespaces. The tags with defined namespace are prefixed by `NAMESPACE-NAME_`. As in example below:
```xml
<?xml version='1.0' ?>
<root xmlns:e="http://www.mycompany.org/eshop">
    <e:orders>
        <e:order>
            <e:id>1</e:id>
            <e:date>2018-01-01</e:date>
            <e:cust_name>David</e:cust_name>	
            <e:order-item>
                <e:price currency="CZK">100</e:price>
                <e:item>Umbrella</e:item>
            </e:order-item>
            <e:order-item>
                <e:price currency="CZK">200</e:price>
                <e:item>Rain Coat</e:item>
            </e:order-item>
        </e:order>
    </e:orders>
</root>
```
The above produces following JSON:
```json
{
	"root": {
		"e-orders": {
			"e-order": {
				"e-id": "1",
				"e-date": "2018-01-01",
				"e-cust_name": "David",
				"e-order-item": [{
						"e-price": {
							"xml_attr_currency": "CZK",
							"txt_content_": "100"
						},
						"e-item": "Umbrella",
						"row_nr": 1
					}, {
						"e-price": {
							"xml_attr_currency": "CZK",
							"txt_content_": "200"
						},
						"e-item": "Rain Coat",
						"row_nr": 2
					}
				]
			}
		}
	}
}
```


**limitations**: namespace must be defined in root. Multiple namespaces do not work at the moment.

### `always_array` parameter

It is crutial specify all tags that may occurr multiple times and hence should be treated as arrays. Not specifying this could lead to unexpected results.

If processor is to process following two files:

**file1**:

```xml
<?xml version='1.0' ?>
<root_el>
    <orders>
        <order>
            <id>1</id>
            <date>2018-01-01</date>
            <cust_name>David</cust_name>	
            <order-item>
                <price currency="CZK">100</price>
                <item>Umbrella</item>
            </order-item>
            <order-item>
                <price currency="CZK">200</price>
                <item>Rain Coat</item>
            </order-item>
        </order>
    </orders>
</root_el>
```
**file2**:

```xml
<?xml version='1.0' ?>
<root_el>
    <orders>
	        <order>
            <id>2</id>
            <date>2018-07-02</date>
            <cust_name>Tom</cust_name>	
            <order-item>
                <price currency="GBP">100</price>
                <item>Sun Screen</item>
            </order-item>
        </order>
    </orders>
</root_el>
```

**configuration**:
```json
{
    "definition": {
        "component": "kds-team.processor-xml2csv"
    },
    "parameters" : {
	"mapping" : {},		
	"append_row_nr" : true,
	"always_array" : [],
	"incremental":true,
	"root_node" : "",
    "in_type": "files"
	}
}
```

the result CSV would be in this form:

The above produces two tables  according to mapping setting `root_el.csv`:

| root_el_orders_order_id | root_el_orders_order_date | root_el_orders_order_cust_name | root_el_orders_order_order-item
|--|--|--|--|
| 1 | 2018-01-01| David | root_el.root_el.orders.order_0a522195d222a8a0dcdc268eadd79625
| 2 | 2018-01-02| Tom | root_el.root_el.orders.order_417d91644a84d2dd7e423f2bdfaa777f


and `root_el_root_el_orders_order_order-item.csv`:

| price_xml_attr_currency_u0 | price_txt_content_u0 | item_u0 | row_nr| JSON_parentId
|--|--|--|--|--|
| CZK | 100| Umbrella |1|root_el.root_el.orders.order_d3859e7943e09800b982215f5c4434c6
| CZK | 200| Rain Coat|2|root_el.root_el.orders.order_d3859e7943e09800b982215f5c4434c6
| GBP | 100| Sun Screen|1|root_el.root_el.orders.order_d3859e7943e09800b982215f5c4434c6

Notice the `_u0` prefix added in order item table, this might be different every time. Adding `order_item` and `order` to `allways_array` parameter will produce consistent results.



## Examples
#### XML example #1
```xml
<?xml version='1.0' ?>
<root_el>
    <orders>
        <order>
            <id>1</id>
            <date>2018-01-01</date>
            <cust_name>David</cust_name>	
            <order-item>
                <price currency="CZK">100</price>
                <item>Umbrella</item>
            </order-item>
            <order-item>
                <price currency="CZK">200</price>
                <item>Rain Coat</item>
            </order-item>
        </order>
        <order>
            <id>2</id>
            <date>2018-07-02</date>
            <cust_name>Tom</cust_name>	
            <order-item>
                <price currency="GBP">100</price>
                <item>Sun Screen</item>
            </order-item>
        </order>
    </orders>
</root_el>
```
### Simple Example

#### XML simple example #1
Assuming XML file in `/in/files/`.
#### Configuration
```json
{
    "definition": {
        "component": "kds-team.processor-xml2csv"
    },
    "parameters" : {
	"mapping" : {},		
	"append_row_nr" : true,
	"always_array" : ["order-item"],
	"incremental":true,
	"root_node" : "",
    "in_type": "files"
	}
}
```
#### Intermediate converted JSON #1
```json
{
	"root_el": {
		"orders": {
			"order": [{
					"id": "1",
					"date": "2018-01-01",
					"cust_name": "David",
					"order-item": [{
							"price": {
								"xml_attr_currency": "CZK",
								"txt_content_": "100"
							},
							"item": "Umbrella",
							"row_nr": 1
						}, {
							"price": {
								"xml_attr_currency": "CZK",
								"txt_content_": "200"
							},
							"item": "Rain Coat",
							"row_nr": 2
						}
					],
					"row_nr": 1
				}, {
					"id": "2",
					"date": "2018-07-02",
					"cust_name": "Tom",
					"order-item": {
						"price": {
							"xml_attr_currency": "GBP",
							"txt_content_": "100"
						},
						"item": "Sun Screen",
						"row_nr": 1
					},
					"row_nr": 2
				}
			]
		}
	}
}

```
The above produces two tables  according to mapping setting `order.csv`:

| root_el_orders_order |
|--|
| root_el.root_el.orders_a91b89e33c2b324f4204686aa64a0d5f |


and `root_el_root_el_orders_order_order-item.csv`:

| price_xml_attr_currency | price_txt_content | item | row_nr| JSON_parentId
|--|--|--|--|--|
| CZK | 100| Umbrella |1|root_el.root_el.orders.order_d3859e7943e09800b982215f5c4434c6
| CZK | 200| Rain Coat|2|root_el.root_el.orders.order_d3859e7943e09800b982215f5c4434c6
| GBP | 100| Sun Screen|1|root_el.root_el.orders.order_d3859e7943e09800b982215f5c4434c6




### Advanced Example 1 - nested arrays, with mapping
Assuming XML file in `/in/files/`.
#### Configuration
```json
{
    "definition": {
        "component": "kds-team.processor-xml2csv"
    },
    "parameters" : {
	"mapping" : {
			"id": {
				"type": "column",
				"mapping": {
					"destination": "order_id",
					"primaryKey": true
				}
			},
			"date": {
				"type": "column",
				"mapping": {
					"destination": "order_date"
				}
			},
			"cust_name": {
				"type": "column",
				"mapping": {
					"destination": "customer_name"
				}
			},
			"order-item": {
				"type": "table",
				"destination": "order-items",
				"parentKey": {
					"primaryKey": true,
					"destination": "order_id"
				},
				"tableMapping": {
					"row_nr": {
						"type": "column",
						"mapping": {
							"destination": "row_nr",

							"primaryKey": true
						}
					},
					"price.xml_attr_currency": {
						"type": "column",
						"mapping": {
							"destination": "currency"
						}
					},
					"price.txt_content_": {
						"type": "column",
						"mapping": {
							"destination": "price_value"
						}
					},
					"item": {
						"type": "column",
						"mapping": {
							"destination": "item_name"
						}
					}
				}
			}},		
	"append_row_nr" : true,
	"always_array" : ["order-item"],
	"incremental":true,
	"root_node" : "root_el.orders.order",
    "in_type": "files"
	}
}
```

#### Intermediate converted JSON #1
```json
[{
		"id": "1",
		"date": "2018-01-01",
		"cust_name": "David",
		"order-item": [{
				"price": {
					"xml_attr_currency": "CZK",
					"txt_content_": "100"
				},
				"item": "Umbrella",
				"row_nr": 1
			}, {
				"price": {
					"xml_attr_currency": "CZK",
					"txt_content_": "200"
				},
				"item": "Rain Coat",
				"row_nr": 2
			}
		],
		"row_nr": 1
	}, {
		"id": "2",
		"date": "2018-07-02",
		"cust_name": "Tom",
		"order-item": {
			"price": {
				"xml_attr_currency": "GBP",
				"txt_content_": "100"
			},
			"item": "Sun Screen",
			"row_nr": 1
		},
		"row_nr": 2
	}
]
```

The above produces two tables  according to mapping setting `order.csv`:

| order_id | order_date | customer_name |
|--|--|--|
| 1 |  2018-01-01| David |
| 2 |  2018-01-02|  Tom|

and `order-items.csv`:

| row_nr | currency | price_value | item_name| order_id
|--|--|--|--|--|
| 1 | CZK| 100 |Umbrella|1
| 2 | CZK| 200|Rain Coat|2
| 1 | GBP| 100|Sun Screen|2




For more information about Generic mapping plese refer to [the generic ex documentation](https://developers.keboola.com/extend/generic-extractor/map/)




For more information about processors, please refer to [the developers documentation](https://developers.keboola.com/extend/component/processors/).


