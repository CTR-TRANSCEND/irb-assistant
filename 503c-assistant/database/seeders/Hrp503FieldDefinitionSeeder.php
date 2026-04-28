<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\FieldDefinition;
use Illuminate\Database\Seeder;

class Hrp503FieldDefinitionSeeder extends Seeder
{
    public function run(): void
    {
        // Curated, stable keys for HRP-503 Protocol/Application form fields.
        // Keys follow the pattern hrp503.{section}.{field}.
        // These definitions do not automatically appear for projects unless
        // a TemplateControlMapping links them to an active template version.
        //
        // Column order: [section, key, label, question_text, required, input_type, sort_order]
        $rows = [

            // -- Protocol Title (cover page) ------------------------------------
            [
                'Protocol Title',
                'hrp503.study.title',
                'Protocol Title',
                'What is the full protocol title?',
                true,
                'text',
                10,
            ],

            // -- Study Summary Table (Section: Protocol Information) ------------
            [
                'Study Summary',
                'hrp503.summary.study_title',
                'Study Title (Summary Table)',
                'What is the study title as it appears in the summary table?',
                true,
                'text',
                100,
            ],
            [
                'Study Summary',
                'hrp503.summary.study_design',
                'Study Design (Summary Table)',
                'Briefly describe the study design (e.g., randomized, open-label, Phase II).',
                true,
                'text',
                110,
            ],
            [
                'Study Summary',
                'hrp503.summary.primary_objective',
                'Primary Objective (Summary Table)',
                'What is the primary study objective as listed in the summary table?',
                true,
                'text',
                120,
            ],
            [
                'Study Summary',
                'hrp503.summary.secondary_objectives',
                'Secondary Objective(s) (Summary Table)',
                'What are the secondary study objectives listed in the summary table?',
                false,
                'textarea',
                130,
            ],
            [
                'Study Summary',
                'hrp503.summary.interventions',
                'Research Intervention(s) / Investigational Agent(s)',
                'What research interventions or investigational agents are being evaluated?',
                true,
                'text',
                140,
            ],
            [
                'Study Summary',
                'hrp503.summary.study_population',
                'Study Population (Summary Table)',
                'Describe the study population (e.g., adults with Type 2 diabetes, healthy volunteers).',
                true,
                'text',
                150,
            ],
            [
                'Study Summary',
                'hrp503.summary.sample_size',
                'Sample Size (Summary Table)',
                'How many subjects are planned for enrollment?',
                false,
                'text',
                160,
            ],

            // -- Section 1: Objectives -----------------------------------------
            [
                'Objectives',
                'hrp503.objectives.aims',
                'Study Aims / Objectives',
                'Describe the purpose, specific aims, or objectives of the study.',
                true,
                'textarea',
                200,
            ],
            [
                'Objectives',
                'hrp503.objectives.hypotheses',
                'Hypotheses to be Tested',
                'State the hypotheses to be tested by this study.',
                false,
                'textarea',
                210,
            ],

            // -- Section 2: Background -----------------------------------------
            [
                'Background',
                'hrp503.background.prior_experience',
                'Prior Experience and Knowledge Gaps',
                'Describe the relevant prior experience of the team and gaps in current knowledge that this study addresses.',
                true,
                'textarea',
                300,
            ],
            [
                'Background',
                'hrp503.background.preliminary_data',
                'Preliminary Data',
                'Describe any relevant preliminary data that support conducting this study.',
                false,
                'textarea',
                310,
            ],
            [
                'Background',
                'hrp503.background.significance',
                'Scientific Background and Significance',
                'Provide the scientific or scholarly background, rationale, and significance of the research based on existing literature. How will this study add to existing knowledge?',
                true,
                'textarea',
                320,
            ],

            // -- Section 3: Study Endpoints ------------------------------------
            [
                'Study Endpoints',
                'hrp503.endpoints.primary_secondary',
                'Primary and Secondary Endpoints',
                'Describe the primary and secondary study endpoints.',
                true,
                'textarea',
                400,
            ],
            [
                'Study Endpoints',
                'hrp503.endpoints.safety',
                'Safety Endpoints',
                'Describe any primary or secondary safety endpoints.',
                false,
                'textarea',
                410,
            ],

            // -- Section 4: Study Intervention ---------------------------------
            [
                'Study Intervention',
                'hrp503.intervention.description',
                'Intervention / Investigational Agent Description',
                'Describe the study intervention and/or investigational agent (e.g., drug, device) being evaluated, including dose, route, and schedule.',
                true,
                'textarea',
                500,
            ],

            // -- Section 5: Procedures Involved --------------------------------
            [
                'Procedures Involved',
                'hrp503.procedures.study_design_description',
                'Study Design and Procedures Description',
                'Describe and explain the study design. Address sub-groups, sub-studies, retrospective data collection, placebos, deception, or washout periods if applicable.',
                true,
                'textarea',
                600,
            ],

            // -- Section 7: Data / Specimen Banking ----------------------------
            [
                'Data and Specimen Banking',
                'hrp503.banking.storage_description',
                'Specimen/Data Banking Storage Description',
                'If specimens or data will be banked for future use, describe where they will be stored, how long they will be kept, how they will be accessed, and who will have access.',
                false,
                'textarea',
                700,
            ],
            [
                'Data and Specimen Banking',
                'hrp503.banking.data_list',
                'Data Associated with Banked Specimens',
                'List the data to be stored and/or associated with each banked specimen.',
                false,
                'textarea',
                710,
            ],
            [
                'Data and Specimen Banking',
                'hrp503.banking.release_procedures',
                'Data/Specimen Release Procedures',
                'Describe the procedures to release data or specimens, including the request process, required approvals, who can obtain data or specimens, and what data is provided with specimens.',
                false,
                'textarea',
                720,
            ],

            // -- Section 9: Inclusion/Exclusion Criteria -----------------------
            [
                'Inclusion and Exclusion Criteria',
                'hrp503.eligibility.screening',
                'Eligibility Screening Procedures',
                'Describe how individuals will be screened for eligibility.',
                true,
                'textarea',
                800,
            ],
            [
                'Inclusion and Exclusion Criteria',
                'hrp503.eligibility.criteria',
                'Inclusion and Exclusion Criteria',
                'Describe the criteria that define who will be included or excluded in your final study sample.',
                true,
                'textarea',
                810,
            ],
            [
                'Inclusion and Exclusion Criteria',
                'hrp503.eligibility.targeted_populations',
                'Targeted or Excluded Populations',
                'If any specific population or segment of community will be targeted or excluded, describe this and provide justification.',
                false,
                'textarea',
                820,
            ],

            // -- Section 11: Number of Subjects --------------------------------
            [
                'Number of Subjects',
                'hrp503.subjects.total_multicenter',
                'Total Subjects Across All Sites',
                'If this is a multicenter study, indicate the total number of subjects to be accrued across all sites.',
                false,
                'text',
                900,
            ],

            // -- Section 12: Recruitment Methods --------------------------------
            [
                'Recruitment Methods',
                'hrp503.recruitment.subject_source',
                'Source of Subjects',
                'Describe the source of subjects (e.g., community, recruitment registry, health records). Include how contact information is obtained and who will contact or approach subjects.',
                true,
                'textarea',
                1000,
            ],

            // -- Section 13: Withdrawal of Subjects ----------------------------
            [
                'Withdrawal of Subjects',
                'hrp503.withdrawal.circumstances',
                'Circumstances for Subject Withdrawal',
                'Describe anticipated circumstances under which subjects will be withdrawn from the research without their consent.',
                false,
                'textarea',
                1100,
            ],

            // -- Section 14: Risks to Subjects ---------------------------------
            [
                'Risks to Subjects',
                'hrp503.risks.foreseeable',
                'Reasonably Foreseeable Risks',
                'Describe the reasonably foreseeable risks and discomforts to subjects, including physical, psychological, social, legal, and economic risks. Include interventions that may be perceived as offensive or embarrassing.',
                true,
                'textarea',
                1200,
            ],
            [
                'Risks to Subjects',
                'hrp503.risks.unforeseeable',
                'Currently Unforeseeable Risks',
                'If applicable, indicate which procedures may have risks to subjects that are currently unforeseeable.',
                false,
                'textarea',
                1210,
            ],
            [
                'Risks to Subjects',
                'hrp503.risks.risks_to_others',
                'Risks to Non-Subjects',
                'If applicable, describe risks to others who are not study subjects.',
                false,
                'textarea',
                1220,
            ],

            // -- Section 15: Potential Benefits --------------------------------
            [
                'Potential Benefits',
                'hrp503.benefits.individual',
                'Potential Benefits to Subjects',
                'Describe the potential benefits that individual subjects may experience from taking part in the research, including the probability, magnitude, and duration of potential benefits.',
                true,
                'textarea',
                1300,
            ],
            [
                'Potential Benefits',
                'hrp503.benefits.no_direct_benefit',
                'Statement of No Direct Benefit',
                'If there is no direct benefit to subjects, state this explicitly. Do not include benefits to society or others.',
                false,
                'textarea',
                1310,
            ],

            // -- Section 16: Data Management and Confidentiality ---------------
            [
                'Data Management and Confidentiality',
                'hrp503.data_management.quality_control',
                'Data Quality Control Procedures',
                'Describe any procedures that will be used for quality control of collected data.',
                false,
                'textarea',
                1400,
            ],

            // -- Section 19: Compensation for Injury ---------------------------
            [
                'Compensation for Injury',
                'hrp503.compensation.injury',
                'Compensation for Research-Related Injury',
                'If the research involves more than minimal risk to subjects, describe the available compensation in the event of a research-related injury.',
                false,
                'textarea',
                1500,
            ],

            // -- Section 21: Consent Process -----------------------------------
            [
                'Consent Process',
                'hrp503.consent.documentation',
                'Written Documentation of Consent',
                'Describe whether and how consent of the subject will be documented in writing.',
                true,
                'textarea',
                1600,
            ],
        ];

        foreach ($rows as $r) {
            [$section, $key, $label, $questionText, $required, $inputType, $sortOrder] = $r;

            FieldDefinition::query()->updateOrCreate(
                ['key' => $key],
                [
                    'label' => $label,
                    'section' => 'HRP-503: '.$section,
                    'sort_order' => (int) $sortOrder,
                    'is_required' => (bool) $required,
                    'input_type' => $inputType,
                    'question_text' => $questionText,
                ],
            );
        }
    }
}
