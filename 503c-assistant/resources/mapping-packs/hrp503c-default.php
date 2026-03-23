<?php

return [
    'name' => 'hrp503c-default',
    'version' => 2,
    'template_sha256' => '470fe073cfb6f3572095e4a323e2ff00b2ffadb1fbec1017abc3f89db5db059f',
    'mappings' => [
        // -- Study Identification --------------------------------------------------
        // Control 0: TITLE field (first SDT in the document).
        [
            'part' => 'document',
            'control_index' => 0,
            'signature_sha256' => '47b5f3636c4d4b2c3709ef790d994c8b12ab2090fec9627dde90b308e11b14d8',
            'field_key' => 'hrp503c.study.title',
        ],

        // -- Personnel & Oversight -------------------------------------------------
        // Control 1: PRINCIPAL INVESTIGATOR (PI).
        [
            'part' => 'document',
            'control_index' => 1,
            'signature_sha256' => '06184b428597988ae7311c5719d546eb8206b565f8a0f9ce8fa02987687f61af',
            'field_key' => 'hrp503c.personnel.pi_name',
        ],
        // Control 2: OVERSIGHT (name and job title of Sanford leader).
        [
            'part' => 'document',
            'control_index' => 2,
            'signature_sha256' => 'e23fd9e7ad2a31f14ee622738df8f052db1d70a65e577b805402e1d8c4e0030d',
            'field_key' => 'hrp503c.oversight.type',
        ],

        // -- Study Design ----------------------------------------------------------
        // Control 9: Q2.0/2.1 "Describe what you want to learn from this project."
        [
            'part' => 'document',
            'control_index' => 9,
            'signature_sha256' => 'e69c4bdee35f11ffa2fe3f355715f980d876a00a534b9044753b5644736f54d9',
            'field_key' => 'hrp503c.design.objectives',
        ],
        // Control 10: Q2.2 "Summarize the study plan."
        [
            'part' => 'document',
            'control_index' => 10,
            'signature_sha256' => 'aa2c8fe4422aeaac698d3809127e19c1a1d518a051bfb9e314b1569941a3900c',
            'field_key' => 'hrp503c.design.summary',
        ],
        // Control 11: Q2.3 "Describe the purpose of doing this project."
        [
            'part' => 'document',
            'control_index' => 11,
            'signature_sha256' => '4d1d9b110f7b5ea181533fb82d0f546c9076cab05b5678e5773f65b414e170ac',
            'field_key' => 'hrp503c.design.purpose',
        ],

        // -- Participants ----------------------------------------------------------
        // Control 12: Q2.4 "Describe the participants" + inclusion criteria.
        [
            'part' => 'document',
            'control_index' => 12,
            'signature_sha256' => '05dfef0f9583054fbedd08ddb32a43fa773240eecb6ba2393d8f00da98e067fd',
            'field_key' => 'hrp503c.participants.population',
        ],
    ],
];
