<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\FieldDefinition;
use Illuminate\Database\Seeder;

/**
 * HRP-398 (WORKSHEET: AI Considerations) curated field definitions.
 *
 * Keys follow the pattern hrp398.{section_id}.{item_id} mirroring the source schema at
 * docs/HRP-398_form_schema.json. HRP-398 is a worksheet (not a fillable form), so every
 * field is non-required; the LLM uses these as guided prompts for AI-related study
 * considerations rather than checkbox/answer controls.
 *
 * Column order: [section, key, label, question_text, required, input_type, sort_order]
 */
class Hrp398FieldDefinitionSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'HRP-398: Institutional Considerations',
                'hrp398.institutional.inst_1',
                'Controversial purposes',
                'Does the study involve "controversial" purposes (e.g., military/lethal use, autonomous weaponry, subliminal manipulation, social credit scoring, real-time remote biometric ID)?',
                false,
                'textarea',
                1010,
            ],

            [
                'HRP-398: Description of AI Technology',
                'hrp398.technology_description.tech_1_name_and_status',
                'Technology name and regulatory status',
                'What is the name of the AI technology / model(s), and what is its regulatory and approval status?',
                false,
                'textarea',
                2010,
            ],
            [
                'HRP-398: Description of AI Technology',
                'hrp398.technology_description.tech_2_purpose',
                'Technology purpose',
                'What is the purpose of the AI technology in this study?',
                false,
                'textarea',
                2020,
            ],
            [
                'HRP-398: Description of AI Technology',
                'hrp398.technology_description.tech_3_availability',
                'Technology availability',
                'Is the AI technology currently commercially available, in development, or otherwise?',
                false,
                'textarea',
                2030,
            ],

            [
                'HRP-398: Model Development & Validation',
                'hrp398.model_dev_validation.mdv_methodology',
                'Transparent methodology',
                'Does the AI technology have a transparent, documented development methodology (training data, validation, performance metrics)?',
                false,
                'textarea',
                3010,
            ],
            [
                'HRP-398: Model Development & Validation',
                'hrp398.model_dev_validation.mdv_purpose',
                'Purpose of the technology',
                'What is the intended clinical or research purpose of the model under development or validation?',
                false,
                'textarea',
                3020,
            ],
            [
                'HRP-398: Model Development & Validation',
                'hrp398.model_dev_validation.mdv_kind',
                'Kind of technology',
                'What kind of AI / ML technology is being utilized (e.g., supervised classifier, generative model, rule-based system)?',
                false,
                'textarea',
                3030,
            ],
            [
                'HRP-398: Model Development & Validation',
                'hrp398.model_dev_validation.mdv_adaptivity',
                'Algorithm adaptivity',
                'Is the algorithm locked or adaptive? If adaptive, how are updates governed and monitored?',
                false,
                'textarea',
                3040,
            ],

            [
                "HRP-398: AI's Purpose in Study",
                'hrp398.ai_purpose_in_study.purpose_phase',
                'Current phase of AI in this protocol',
                'What is the AI technology\'s CURRENT phase in this specific protocol application (e.g., training, validation, deployment)?',
                false,
                'textarea',
                4010,
            ],
            [
                "HRP-398: AI's Purpose in Study",
                'hrp398.ai_purpose_in_study.purpose_role',
                'Role of AI in meeting study aims',
                'What is the ROLE of the AI in meeting the aims of the study?',
                false,
                'textarea',
                4020,
            ],
            [
                "HRP-398: AI's Purpose in Study",
                'hrp398.ai_purpose_in_study.purpose_inform_or_drive',
                'Inform vs drive decisions',
                'Is the AI technology intended to "inform" decisions (advisory) or to "drive" decisions (autonomous), for medical or non-medical purposes?',
                false,
                'textarea',
                4030,
            ],

            [
                'HRP-398: IRB Review Determination',
                'hrp398.irb_review_required.irb_1',
                'Human Research Determination',
                'Does this study require IRB review? Refer to HRP-310 (WORKSHEET — Human Research Determination) and summarize the determination.',
                false,
                'textarea',
                5010,
            ],

            [
                'HRP-398: FDA Device Determination',
                'hrp398.fda_regulation.fda_1',
                'FDA device determination',
                'Is the AI technology possibly regulated by FDA? Refer to HRP-307a (Device Determinations) and HRP-307b (IRB Review of Devices) and summarize the determination.',
                false,
                'textarea',
                6010,
            ],

            [
                'HRP-398: Misc. Considerations',
                'hrp398.misc_considerations.misc_future_modifications',
                'Future modifications',
                'Can the protocol be designed broadly enough so that future model modifications fit within the approved scope?',
                false,
                'textarea',
                9010,
            ],
            [
                'HRP-398: Misc. Considerations',
                'hrp398.misc_considerations.misc_accountability',
                'Accountability and public deployment',
                'How is the AI technology designed and implemented for accountability when used in publicly accessible spaces?',
                false,
                'textarea',
                9020,
            ],
        ];

        foreach ($rows as $r) {
            [$section, $key, $label, $questionText, $required, $inputType, $sortOrder] = $r;

            FieldDefinition::query()->updateOrCreate(
                ['key' => $key],
                [
                    'label' => $label,
                    'section' => $section,
                    'sort_order' => (int) $sortOrder,
                    'is_required' => (bool) $required,
                    'input_type' => $inputType,
                    'question_text' => $questionText,
                ],
            );
        }
    }
}
