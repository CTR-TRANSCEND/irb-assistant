<?php

// Bundled mapping pack for the VCU HRP-503 Protocol/Application template.
//
// Control indices 0-27 cover the protocol title field (0) and the revision
// history table cells (1-27); the summary table starts at index 28.
// Meaningful narrative sections begin around index 38.
//
// Each entry maps one template SDT control to one FieldDefinition key.
// Match logic: control_index + signature_sha256 (both must match).
//
// template_sha256 is the SHA-256 of docs/HRP-503-TEMPLATE-PROTOCOL.docx so
// this pack is only applied to that exact file version.

return [
    'name' => 'hrp503-default',
    'version' => 1,
    'template_sha256' => '1c26f1893830efeb99fba14ca0e1cf5606d785017a36e689c1042c16bfe2ea8d',
    'mappings' => [

        // -- Protocol Title (cover page) ------------------------------------------
        // Control 0: "PROTOCOL TITLE:" label before the SDT.
        [
            'part' => 'document',
            'control_index' => 0,
            'signature_sha256' => '2834692a1242a5d42c643a32ee88e248e047ac94afd3ad780f4c8bb3ff61a8d1',
            'field_key' => 'hrp503.study.title',
        ],

        // -- Study Summary table (indices 28-37) -----------------------------------
        // Control 28: Study Title cell in the summary table.
        [
            'part' => 'document',
            'control_index' => 28,
            'signature_sha256' => '1d3e62949b4a8860bf8efed525e8d5e7bda6d681a222c8a34e00d93077291286',
            'field_key' => 'hrp503.summary.study_title',
        ],
        // Control 29: Study Design cell in the summary table.
        [
            'part' => 'document',
            'control_index' => 29,
            'signature_sha256' => '60ddf7c2cbe83193281874a2c0a373d5efe763fbc07fc33dfcf4c1cb1601bcc3',
            'field_key' => 'hrp503.summary.study_design',
        ],
        // Control 30: Primary Objective cell in the summary table.
        [
            'part' => 'document',
            'control_index' => 30,
            'signature_sha256' => 'fd3601a667b27bd29c2bff4156b6afd561b423fa831fc4431310f0f623b85fd8',
            'field_key' => 'hrp503.summary.primary_objective',
        ],
        // Control 31: Secondary Objective(s) cell in the summary table.
        [
            'part' => 'document',
            'control_index' => 31,
            'signature_sha256' => '3528fefc177082a2254bcd08a8991f32a4730350032df301c8d1d95aa42a39b4',
            'field_key' => 'hrp503.summary.secondary_objectives',
        ],
        // Control 32: Research Intervention(s) / Investigational Agent(s) cell.
        [
            'part' => 'document',
            'control_index' => 32,
            'signature_sha256' => '777c8421fc72a7003ddeed62f58eadd0135719b914f2b40074a640c422693d59',
            'field_key' => 'hrp503.summary.interventions',
        ],
        // Control 34: Study Population cell in the summary table.
        [
            'part' => 'document',
            'control_index' => 34,
            'signature_sha256' => 'f8dbf2f40534131ec2a9ae19ba11076e7478a491ca1095db56a26c351dad245f',
            'field_key' => 'hrp503.summary.study_population',
        ],
        // Control 35: Sample Size cell in the summary table.
        [
            'part' => 'document',
            'control_index' => 35,
            'signature_sha256' => 'c3a33e1bbd8207522d8b8ada06949b4d7590eb0c9e877844eb5d6d777d2e5696',
            'field_key' => 'hrp503.summary.sample_size',
        ],

        // -- Section 1: Objectives ------------------------------------------------
        // Control 38: "Describe the purpose, specific aims, or objectives."
        [
            'part' => 'document',
            'control_index' => 38,
            'signature_sha256' => '674f7d04e756cae38dad36bf18f0d4e354e4831e53a3152d2e35c849be0d1120',
            'field_key' => 'hrp503.objectives.aims',
        ],
        // Control 39: "State the hypotheses to be tested."
        [
            'part' => 'document',
            'control_index' => 39,
            'signature_sha256' => 'e095577f5620cc217e186da6565438ae2c1bcc1529d555f40a338b2def961873',
            'field_key' => 'hrp503.objectives.hypotheses',
        ],

        // -- Section 2: Background ------------------------------------------------
        // Control 40: "Describe the relevant prior experience and gaps in current knowledge."
        [
            'part' => 'document',
            'control_index' => 40,
            'signature_sha256' => '3f8d454b98b59fbfb70bc0286ca0e6ae41bdcfd5cd45a2bd70221915b3f04bb0',
            'field_key' => 'hrp503.background.prior_experience',
        ],
        // Control 41: "Describe any relevant preliminary data."
        [
            'part' => 'document',
            'control_index' => 41,
            'signature_sha256' => 'dbe2da60585f31f385261c80b074c2b0204c21f1f8a4ee137c00d6ae3abed940',
            'field_key' => 'hrp503.background.preliminary_data',
        ],
        // Control 42: "Provide the scientific or scholarly background for, rationale for,
        //              and significance of the research..."
        [
            'part' => 'document',
            'control_index' => 42,
            'signature_sha256' => '036cc4ea9b1e2b8ec4b4f5157269c62ede1382d3824b66526598319f216b967c',
            'field_key' => 'hrp503.background.significance',
        ],

        // -- Section 3: Study Endpoints -------------------------------------------
        // Control 43: "Describe the primary and secondary study endpoints."
        [
            'part' => 'document',
            'control_index' => 43,
            'signature_sha256' => 'b4914293e18ae2074552471fb78dc30742189ddb1bfded2cc35c9511ce7fe368',
            'field_key' => 'hrp503.endpoints.primary_secondary',
        ],
        // Control 44: "Describe any primary or secondary safety endpoints."
        [
            'part' => 'document',
            'control_index' => 44,
            'signature_sha256' => 'cfd454faaec751fa35bb0b16f7e20ea9385be58732934a4e687538dfc5c0bcbc',
            'field_key' => 'hrp503.endpoints.safety',
        ],

        // -- Section 4: Study Intervention ----------------------------------------
        // Control 45: "Describe the study intervention and/or investigational agent."
        [
            'part' => 'document',
            'control_index' => 45,
            'signature_sha256' => '5d16dbe37e54e473f9b5d02bfdcb68dedfd81879171aca0507b8ebc5c5bd7ff0',
            'field_key' => 'hrp503.intervention.description',
        ],

        // -- Section 5: Procedures Involved ---------------------------------------
        // Control 48: "Describe and explain the study design. If you have any sub-groups..."
        [
            'part' => 'document',
            'control_index' => 48,
            'signature_sha256' => '677628fa9f4139fae407fb321eed9936fc41acb1035c8bbdff2b333111db39ca',
            'field_key' => 'hrp503.procedures.study_design_description',
        ],

        // -- Section 7: Data/Specimen Banking -------------------------------------
        // Control 54: Banking location, storage duration, and access description.
        [
            'part' => 'document',
            'control_index' => 54,
            'signature_sha256' => '6989f23e68dde2405a51b63fd72bada17e4b6a803478746ad25211257dc98437',
            'field_key' => 'hrp503.banking.storage_description',
        ],
        // Control 55: "List the data to be stored and/or associated with each specimen."
        [
            'part' => 'document',
            'control_index' => 55,
            'signature_sha256' => '662f03a935e70f0b8778c545991682714cfba35693b8687bafd62aa2e81e8f13',
            'field_key' => 'hrp503.banking.data_list',
        ],
        // Control 56: "Describe the procedures to release data or specimens."
        [
            'part' => 'document',
            'control_index' => 56,
            'signature_sha256' => '98fa490d7c11c9ff3ca23a9315b38a7fc645d91dfbc2829e01652e8dedda6d25',
            'field_key' => 'hrp503.banking.release_procedures',
        ],

        // -- Section 9: Inclusion/Exclusion Criteria ------------------------------
        // Control 61: "Describe how individuals will be screened for eligibility."
        [
            'part' => 'document',
            'control_index' => 61,
            'signature_sha256' => 'effd0dc17611b7f4ff039a762645bc9a01ed766d2fe697c0d3f507a1c8ed190f',
            'field_key' => 'hrp503.eligibility.screening',
        ],
        // Control 62: "Describe the criteria that define who will be included or excluded."
        [
            'part' => 'document',
            'control_index' => 62,
            'signature_sha256' => '9b3e4ab05bb4a546d9bb88d886816a66e34a2401e00e13e21be562310bbda09f',
            'field_key' => 'hrp503.eligibility.criteria',
        ],
        // Control 63: Targeted or excluded populations — justification.
        [
            'part' => 'document',
            'control_index' => 63,
            'signature_sha256' => '8390f39cd19a163945f3da4b42472cc6a0b23e91df279a0a362aef62c1f4dc03',
            'field_key' => 'hrp503.eligibility.targeted_populations',
        ],

        // -- Section 11: Number of Subjects ---------------------------------------
        // Control 105: Total subjects across all sites (multicenter field).
        [
            'part' => 'document',
            'control_index' => 105,
            'signature_sha256' => 'bfb6e3745f94bcda259f7d7f739e250751f1d4aca4418cd278fa5174124dcd86',
            'field_key' => 'hrp503.subjects.total_multicenter',
        ],

        // -- Section 12: Recruitment Methods --------------------------------------
        // Control 68: "Describe the source of subjects."
        [
            'part' => 'document',
            'control_index' => 68,
            'signature_sha256' => 'dad2a5703676755c3f08a823cfbb4a33417d6294b84bbb7743f3713f471ad431',
            'field_key' => 'hrp503.recruitment.subject_source',
        ],

        // -- Section 13: Withdrawal of Subjects -----------------------------------
        // Control 72: "Describe anticipated circumstances under which subjects will be withdrawn."
        [
            'part' => 'document',
            'control_index' => 72,
            'signature_sha256' => '93117894533048f9ca70072c199590768e3394edb2abc2dcf1d74043483bf540',
            'field_key' => 'hrp503.withdrawal.circumstances',
        ],

        // -- Section 14: Risks to Subjects ----------------------------------------
        // Control 75: Reasonably foreseeable risks (physical, psychological, social, legal,
        //             economic; offensive/embarrassing interventions).
        [
            'part' => 'document',
            'control_index' => 75,
            'signature_sha256' => '73d49fc416d4b37d57cb0df96b8532ec33a8fe14889c5612a0051bec845affe9',
            'field_key' => 'hrp503.risks.foreseeable',
        ],
        // Control 76: "Indicate which procedures may have risks that are currently unforeseeable."
        [
            'part' => 'document',
            'control_index' => 76,
            'signature_sha256' => '94d4b9ccd96583d53e982f8a150f0f1605e8208fd8e3c4e6ada2f3967a6f6c44',
            'field_key' => 'hrp503.risks.unforeseeable',
        ],
        // Control 78: "If applicable, describe risks to others who are not subjects."
        [
            'part' => 'document',
            'control_index' => 78,
            'signature_sha256' => '3a68773fccce1c3632999dc071e28965e6015840cbce6fb88210a70218fe821d',
            'field_key' => 'hrp503.risks.risks_to_others',
        ],

        // -- Section 15: Potential Benefits ----------------------------------------
        // Control 80: "Describe the potential benefits that individual subjects may experience."
        [
            'part' => 'document',
            'control_index' => 80,
            'signature_sha256' => '4fea4f9e3d5e9a8399310625dd8e10b499c17247ea439b2f1ec6533a34fa6683',
            'field_key' => 'hrp503.benefits.individual',
        ],
        // Control 81: "Indicate if there is no direct benefit."
        [
            'part' => 'document',
            'control_index' => 81,
            'signature_sha256' => '0139aa56df721ec50826c99d40f39bbcd2de1ff4b3f3b400bafc485479636dea',
            'field_key' => 'hrp503.benefits.no_direct_benefit',
        ],

        // -- Section 16: Data Management and Confidentiality -----------------------
        // Control 85: "Describe any procedures that will be used for quality control of
        //              collected data."
        [
            'part' => 'document',
            'control_index' => 85,
            'signature_sha256' => '31681c9403401fd68819d1f43d744c64f0d32ad63c2efd302047d02c29088587',
            'field_key' => 'hrp503.data_management.quality_control',
        ],

        // -- Section 19: Compensation for Injury -----------------------------------
        // Control 91: Compensation available in the event of research-related injury.
        [
            'part' => 'document',
            'control_index' => 91,
            'signature_sha256' => '6eca3e9d6089920ff4ce95d3543bae6c0045757d49b27ee8e133c7ecf4d4a1b4',
            'field_key' => 'hrp503.compensation.injury',
        ],

        // -- Section 21: Consent Process ------------------------------------------
        // Control 100: "Describe whether and how consent of the subject will be documented
        //               in writing."
        [
            'part' => 'document',
            'control_index' => 100,
            'signature_sha256' => '3cdca7bb1beef85b63ddaac63aee03405a4f3c9fde3da42da0128368fd0874df',
            'field_key' => 'hrp503.consent.documentation',
        ],
    ],
];
