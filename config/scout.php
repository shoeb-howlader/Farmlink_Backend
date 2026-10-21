<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Search Engine
    |--------------------------------------------------------------------------
    |
    | This option controls the default search connection that gets used while
    | using Laravel Scout. This connection is used when syncing all models
    | to the search service. You should adjust this based on your needs.
    |
    | Supported: "algolia", "meilisearch", "typesense", "turbopuffer",
    |            "database", "collection", "null"
    |
    */

    'driver' => env('SCOUT_DRIVER', 'collection'),

    /*
    |--------------------------------------------------------------------------
    | Index Prefix
    |--------------------------------------------------------------------------
    |
    | Here you may specify a prefix that will be applied to all search index
    | names used by Scout. This prefix may be useful if you have multiple
    | "tenants" or applications sharing the same search infrastructure.
    |
    */

    'prefix' => env('SCOUT_PREFIX', ''),

    /*
    |--------------------------------------------------------------------------
    | Queue Data Syncing
    |--------------------------------------------------------------------------
    |
    | This option allows you to control if the operations that sync your data
    | with your search engines are queued. When this is set to "true" then
    | all automatic data syncing will get queued for better performance.
    |
    */

    'queue' => env('SCOUT_QUEUE', false),

    /*
    |--------------------------------------------------------------------------
    | Database Transactions
    |--------------------------------------------------------------------------
    |
    | This configuration option determines if your data will only be synced
    | with your search indexes after every open database transaction has
    | been committed, thus preventing any discarded data from syncing.
    |
    */

    'after_commit' => true,

    /*
    |--------------------------------------------------------------------------
    | Chunk Sizes
    |--------------------------------------------------------------------------
    |
    | These options allow you to control the maximum chunk size when you are
    | mass importing data into the search engine. This allows you to fine
    | tune each of these chunk sizes based on the power of the servers.
    |
    */

    'chunk' => [
        'searchable' => 500,
        'unsearchable' => 500,
    ],

    /*
    |--------------------------------------------------------------------------
    | Soft Deletes
    |--------------------------------------------------------------------------
    |
    | This option allows to control whether to keep soft deleted records in
    | the search indexes. Maintaining soft deleted records can be useful
    | if your application still needs to search for the records later.
    |
    */

    'soft_delete' => false,

    /*
    |--------------------------------------------------------------------------
    | Identify User
    |--------------------------------------------------------------------------
    |
    | This option allows you to control whether to notify the search engine
    | of the user performing the search. This is sometimes useful if the
    | engine supports any analytics based on this application's users.
    |
    | Supported engines: "algolia"
    |
    */

    'identify' => env('SCOUT_IDENTIFY', false),

    /*
    |--------------------------------------------------------------------------
    | Algolia Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your Algolia settings. Algolia is a cloud hosted
    | search engine which works great with Scout out of the box. Just plug
    | in your application ID and admin API key to get started searching.
    |
    */

    'algolia' => [
        'id' => env('ALGOLIA_APP_ID', ''),
        'secret' => env('ALGOLIA_SECRET', ''),
        'index-settings' => [
            // 'users' => [
            //     'searchableAttributes' => ['id', 'name', 'email'],
            //     'attributesForFaceting'=> ['filterOnly(email)'],
            // ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Meilisearch Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your Meilisearch settings. Meilisearch is an open
    | source search engine with minimal configuration. Below, you can state
    | the host and key information for your own Meilisearch installation.
    |
    | See: https://www.meilisearch.com/docs/learn/configuration/instance_options#all-instance-options
    |
    */

    'meilisearch' => [
        'host' => env('MEILISEARCH_HOST', 'http://localhost:7700'),
        'key' => env('MEILISEARCH_KEY'),
        'index-settings' => [
            \App\Models\Product::class => [
                'filterableAttributes' => [
                    'id',
                    'category',
                    'is_active',
                    'is_featured',
                    'price',
                    'has_stock',
                ],
                'sortableAttributes' => [
                    'price',
                    'name',
                    'created_at',
                ],
                'searchableAttributes' => [
                    'name',
                    'name_bn',
                    'category',
                    'category_bn',
                    'short_description',
                    'short_description_bn',
                    'description',
                    'description_bn',
                ],
                'rankingRules' => [
                    'words',
                    'typo',
                    'proximity',
                    'attribute',
                    'sort',
                    'has_stock:desc',
                    'is_featured:desc',
                    'exactness',
                ],
                'synonyms' => [
                    'shrimp' => ['chingri', 'বাগদা', 'গলদা', 'চিংড়ি', 'prawn', 'shrimps'],
                    'chingri' => ['shrimp', 'বাগদা', 'গলদা', 'চিংড়ি', 'prawn'],
                    'feed' => ['খাবার', 'ফিড', 'food', 'ration', 'pellet'],
                    'khabar' => ['feed', 'খাবার', 'ফিড'],
                    'medicine' => ['ঔষধ', 'ওষুধ', 'treatment', 'drug', 'antibiotic'],
                    'oxygen' => ['অক্সিজেন', 'aerator', 'o2', 'air', 'dissolved oxygen', 'oxymore'],
                    'probiotic' => ['বায়োফ্লক', 'ব্যাকটেরিয়া', 'probiotics', 'biofloc', 'water care'],
                    'pond' => ['ঘের', 'পুকুর', 'gher', 'dighi'],
                    'mineral' => ['মিনারেল', 'লবণ', 'minerals', 'salts'],
                    'fertilizer' => ['সার', 'fertilizers', 'urea', 'tsp'],
                ],
                'typoTolerance' => [
                    'enabled' => true,
                    'minWordSizeForTypos' => [
                        'oneTypo' => 4,
                        'twoTypos' => 8,
                    ],
                ],
            ],

            \App\Models\User::class => [
                'filterableAttributes' => [
                    'id',
                    'district',
                    'roles',
                    'is_farmer',
                    'is_active',
                    'is_blacklisted',
                    'cod_blocked',
                    'consultations_blocked',
                ],
                'sortableAttributes' => [
                    'id',
                    'name',
                    'created_at',
                ],
                'searchableAttributes' => [
                    'name',
                    'phone',
                    'email',
                    'district',
                ],
                'typoTolerance' => [
                    'enabled' => true,
                    'disableOnAttributes' => [
                        'phone',
                        'id',
                    ],
                ],
            ],

            \App\Models\Order::class => [
                'filterableAttributes' => [
                    'id',
                    'user_id',
                    'status',
                    'district',
                    'created_at',
                ],
                'sortableAttributes' => [
                    'id',
                    'total',
                    'created_at',
                ],
                'searchableAttributes' => [
                    'order_code',
                    'invoice_number',
                    'recipient_name',
                    'recipient_phone',
                    'farmer_name',
                    'farmer_phone',
                    'district',
                ],
                'typoTolerance' => [
                    'enabled' => true,
                    'disableOnAttributes' => [
                        'id',
                        'order_code',
                        'invoice_number',
                        'recipient_phone',
                        'farmer_phone',
                    ],
                ],
            ],

            \App\Models\Farm::class => [
                'filterableAttributes' => [
                    'id',
                    'user_id',
                    'district',
                    'upazila',
                    'farm_type',
                ],
                'sortableAttributes' => [
                    'id',
                    'total_area',
                    'created_at',
                ],
                'searchableAttributes' => [
                    'farm_name',
                    'district',
                    'upazila',
                    'union',
                    'village',
                    'farmer_name',
                    'farmer_phone',
                ],
                'typoTolerance' => [
                    'enabled' => true,
                    'disableOnAttributes' => [
                        'id',
                        'farmer_phone',
                    ],
                ],
            ],

            \App\Models\Message::class => [
                'filterableAttributes' => [
                    'id',
                    'conversation_id',
                    'sender_id',
                    'has_attachment',
                ],
                'sortableAttributes' => [
                    'id',
                    'created_at',
                ],
                'searchableAttributes' => [
                    'body',
                    'sender_name',
                ],
                'rankingRules' => [
                    'words',
                    'typo',
                    'proximity',
                    'attribute',
                    'sort',
                    'exactness',
                ],
                'typoTolerance' => [
                    'enabled' => true,
                    'minWordSizeForTypos' => [
                        'oneTypo' => 4,
                        'twoTypos' => 8,
                    ],
                ],
            ],
        ],
        'model-settings' => [
            // User::class => [
            //     'embedding' => [
            //         'embedder' => 'default',
            //         'dimensions' => 1536,
            //     ],
            // ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Typesense Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your Typesense settings. Typesense is an open
    | source search engine using minimal configuration. Below, you will
    | state the host, key, and schema configuration for the instance.
    |
    */

    'typesense' => [
        'client-settings' => [
            'api_key' => env('TYPESENSE_API_KEY', 'xyz'),
            'nodes' => [
                [
                    'host' => env('TYPESENSE_HOST', 'localhost'),
                    'port' => env('TYPESENSE_PORT', '8108'),
                    'path' => env('TYPESENSE_PATH', ''),
                    'protocol' => env('TYPESENSE_PROTOCOL', 'http'),
                ],
            ],
            'nearest_node' => [
                'host' => env('TYPESENSE_HOST', 'localhost'),
                'port' => env('TYPESENSE_PORT', '8108'),
                'path' => env('TYPESENSE_PATH', ''),
                'protocol' => env('TYPESENSE_PROTOCOL', 'http'),
            ],
            'connection_timeout_seconds' => env('TYPESENSE_CONNECTION_TIMEOUT_SECONDS', 2),
            'healthcheck_interval_seconds' => env('TYPESENSE_HEALTHCHECK_INTERVAL_SECONDS', 30),
            'num_retries' => env('TYPESENSE_NUM_RETRIES', 3),
            'retry_interval_seconds' => env('TYPESENSE_RETRY_INTERVAL_SECONDS', 1),
        ],
        // 'max_total_results' => env('TYPESENSE_MAX_TOTAL_RESULTS', 1000),
        'model-settings' => [
            // User::class => [
            //     'collection-schema' => [
            //         'fields' => [
            //             [
            //                 'name' => 'id',
            //                 'type' => 'string',
            //             ],
            //             [
            //                 'name' => 'name',
            //                 'type' => 'string',
            //             ],
            //             [
            //                 'name' => 'created_at',
            //                 'type' => 'int64',
            //             ],
            //         ],
            //         'default_sorting_field' => 'created_at',
            //     ],
            //     'search-parameters' => [
            //         'query_by' => 'name'
            //     ],
            //     'embedding' => [
            //         'attribute' => 'embedding',
            //         'dimensions' => 1536,
            //     ],
            // ],
        ],
        'import_action' => env('TYPESENSE_IMPORT_ACTION', 'upsert'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Turbopuffer Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your Turbopuffer connection and the schema and
    | searchable attributes defined by each of your application's models.
    | Turbopuffer is a scalable engine with full-text + vector search.
    |
    */

    'turbopuffer' => [
        'api_key' => env('TURBOPUFFER_API_KEY'),
        'region' => env('TURBOPUFFER_REGION', 'gcp-us-central1'),
        'base_url' => env('TURBOPUFFER_BASE_URL'),
        'timeout' => env('TURBOPUFFER_TIMEOUT', 60),
        'connect_timeout' => env('TURBOPUFFER_CONNECT_TIMEOUT', 5),
        'retries' => env('TURBOPUFFER_RETRIES', 3),
        'model-settings' => [
            // User::class => [
            //     'searchable-attributes' => [
            //         'name' => 2,
            //         'email' => 1,
            //     ],
            //     'embedding' => [
            //         'attribute' => 'embedding',
            //         'dimensions' => 1536,
            //     ],
            //     'schema' => [
            //         'name' => ['type' => 'string', 'full_text_search' => true],
            //         'email' => ['type' => 'string', 'full_text_search' => true],
            //         'embedding' => ['type' => '[1536]f32', 'ann' => true],
            //     ],
            // ],
        ],
    ],

];
