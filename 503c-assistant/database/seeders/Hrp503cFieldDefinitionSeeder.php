<?php

namespace Database\Seeders;

use App\Models\FieldDefinition;
use Illuminate\Database\Seeder;

class Hrp503cFieldDefinitionSeeder extends Seeder
{
    public function run(): void
    {
        // Curated, stable keys for core HRP-503c fields.
        // These do not automatically appear for projects unless mapped to the active template.
        $rows = [
            ['Study Identification', 'hrp503c.study.title', 'Study Title', 'What is the full study title?', true, 'text', 10],
            ['Study Identification', 'hrp503c.study.short_title', 'Short Title / Acronym', 'What is the short title or acronym?', false, 'text', 20],

            ['Personnel & Oversight', 'hrp503c.personnel.pi_name', 'Principal Investigator (PI)', 'Who is the Principal Investigator?', true, 'text', 100],
            ['Personnel & Oversight', 'hrp503c.personnel.pi_email', 'PI Email', 'What is the PI email address?', false, 'text', 110],
            ['Personnel & Oversight', 'hrp503c.personnel.pi_department', 'PI Department', 'What department/unit is the PI in?', false, 'text', 120],
            ['Personnel & Oversight', 'hrp503c.oversight.type', 'Oversight', 'What oversight applies (e.g., exempt/expedited/full board)?', true, 'text', 130],

            ['Funding & Sites', 'hrp503c.funding.source', 'Funding Source', 'Who is funding this study?', false, 'text', 200],
            ['Funding & Sites', 'hrp503c.sites.locations', 'Study Site(s)', 'Where will study activities occur?', false, 'textarea', 210],

            ['Study Design', 'hrp503c.design.purpose', 'Study Purpose', 'What is the purpose of the study?', true, 'textarea', 300],
            ['Study Design', 'hrp503c.design.objectives', 'Study Objectives', 'What are the primary objectives or aims?', true, 'textarea', 310],
            ['Study Design', 'hrp503c.design.summary', 'Study Design Summary', 'Summarize the study design and methods.', true, 'textarea', 320],
            ['Study Design', 'hrp503c.design.procedures', 'Study Procedures', 'What procedures/interventions will participants undergo?', true, 'textarea', 330],

            ['Participants', 'hrp503c.participants.population', 'Participant Population', 'Who are the target participants?', true, 'textarea', 400],
            ['Participants', 'hrp503c.participants.enrollment_target', 'Target Enrollment', 'How many participants are planned?', false, 'number', 410],
            ['Participants', 'hrp503c.participants.inclusion', 'Inclusion Criteria', 'What are the inclusion criteria?', true, 'textarea', 420],
            ['Participants', 'hrp503c.participants.exclusion', 'Exclusion Criteria', 'What are the exclusion criteria?', false, 'textarea', 430],
            ['Participants', 'hrp503c.participants.vulnerable_groups', 'Vulnerable Populations', 'Are vulnerable populations included? If yes, describe.', false, 'textarea', 440],

            ['Risk/Benefit', 'hrp503c.risk_benefit.risks', 'Risks', 'What are the reasonably foreseeable risks/discomforts?', true, 'textarea', 500],
            ['Risk/Benefit', 'hrp503c.risk_benefit.benefits', 'Benefits', 'What benefits are expected (to participants or society)?', true, 'textarea', 510],

            ['Privacy & Consent', 'hrp503c.privacy.confidentiality', 'Privacy/Confidentiality Protections', 'How will privacy and confidentiality be protected?', true, 'textarea', 600],
            ['Privacy & Consent', 'hrp503c.privacy.data_sharing', 'Data Sharing Plan', 'Will identifiable/de-identified data be shared? Describe.', false, 'textarea', 610],
            ['Privacy & Consent', 'hrp503c.consent.process', 'Consent Process', 'How and when will consent be obtained/documented?', true, 'textarea', 620],

            ['Timeline', 'hrp503c.timeline.start_date', 'Study Start Date', 'What is the anticipated study start date?', false, 'date', 700],
            ['Timeline', 'hrp503c.timeline.end_date', 'Study End Date', 'What is the anticipated study end date?', false, 'date', 710],
        ];

        foreach ($rows as $r) {
            [$section, $key, $label, $questionText, $required, $inputType, $sortOrder] = $r;

            FieldDefinition::query()->updateOrCreate(
                ['key' => $key],
                [
                    'label' => $label,
                    'section' => 'HRP-503c: '.$section,
                    'sort_order' => (int) $sortOrder,
                    'is_required' => (bool) $required,
                    'input_type' => $inputType,
                    'question_text' => $questionText,
                ],
            );
        }
    }
}
