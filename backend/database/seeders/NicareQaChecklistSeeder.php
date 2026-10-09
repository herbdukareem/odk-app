<?php

namespace Database\Seeders;

use App\Models\AssessmentForm;
use App\Models\AssessmentFormField;
use App\Models\AssessmentFormFieldOption;
use App\Models\AssessmentFormRule;
use App\Models\AssessmentFormSection;
use App\Models\AssessmentFormVersion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NicareQaChecklistSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $sections = $this->sections();
            $fields = [
                ...$this->metadataFields(),
                ...$this->personnelFields(),
                ...$this->drugAndStockFields(),
                ...$this->laboratoryFields(),
                ...$this->otherServicesFields(),
                ...$this->infectionControlFields(),
                ...$this->healthInformationFields(),
                ...$this->financialManagementFields(),
                ...$this->patientInteractionFields(),
                ...$this->evidenceFields(),
                ...$this->signatureFields(),
            ];

            $validationRules = $this->validationRules($fields);
            $visibilityRules = $this->visibilityRules($fields);
            $scoringRules = $this->scoringRules($fields);
            $scoringMaxScore = $this->maxScore($scoringRules);

            $form = AssessmentForm::firstOrNew(['slug' => 'nicare-qa-checklist']);
            if (!$form->exists) {
                $form->uuid = (string) Str::uuid();
            }
            $form->fill([
                'title' => 'NiCare QA Checklist',
                'description' => 'Comprehensive facility assessment covering infrastructure, services, staff, supplies and regulatory compliance.',
                'target_audience' => 'Facility assessment teams',
                'requires_scoring' => true,
                'scoring_max_score' => $scoringMaxScore,
                'status' => AssessmentForm::STATUS_PUBLISHED,
                'published_at' => $form->published_at ?? now(),
            ])->save();

            $this->syncBuilderRecords(
                form: $form,
                sections: $sections,
                fields: $fields,
                validationRules: $validationRules,
                visibilityRules: $visibilityRules,
                evidenceRules: $this->requiredEvidenceRules(),
                signatureRules: $this->signatureRules(),
                reviewFlags: $this->reviewFlags(),
            );

            $version = AssessmentFormVersion::updateOrCreate(
                ['assessment_form_id' => $form->id, 'version' => 1],
                [
                    'status' => AssessmentFormVersion::STATUS_PUBLISHED,
                    'offline_enabled' => true,
                    'metadata_schema' => [
                        'facility_source' => 'new-web',
                        'requires_facility_snapshot' => true,
                        'requires_assessor_snapshot' => true,
                        'requires_device_snapshot' => true,
                        'requires_scoring' => true,
                        'scoring_max_score' => $scoringMaxScore,
                    ],
                    'requires_scoring' => true,
                    'scoring_max_score' => $scoringMaxScore,
                    'sections' => $sections,
                    'fields' => $fields,
                    'validation_rules' => $validationRules,
                    'visibility_rules' => $visibilityRules,
                    'scoring_rules' => $scoringRules,
                    'required_evidence_rules' => $this->requiredEvidenceRules(),
                    'signature_rules' => $this->signatureRules(),
                    'review_flags' => $this->reviewFlags(),
                    'published_at' => now(),
                ]
            );

            $form->forceFill([
                'current_version_id' => $version->id,
                'requires_scoring' => true,
                'scoring_max_score' => $scoringMaxScore,
                'status' => AssessmentForm::STATUS_PUBLISHED,
                'published_at' => $form->published_at ?? now(),
            ])->save();
        });
    }

    private function syncBuilderRecords(
        AssessmentForm $form,
        array $sections,
        array $fields,
        array $validationRules,
        array $visibilityRules,
        array $evidenceRules,
        array $signatureRules,
        array $reviewFlags,
    ): void {
        $sectionModels = [];
        foreach ($sections as $section) {
            $sectionModel = AssessmentFormSection::withTrashed()->firstOrNew([
                'assessment_form_id' => $form->id,
                'key' => $section['key'],
            ]);
            $sectionModel->fill([
                'label' => $section['label'],
                'description' => $section['description'] ?? null,
                'sort_order' => $section['order'] ?? 0,
                'is_repeatable' => (bool) ($section['is_repeatable'] ?? false),
                'min_repeats' => $section['min_repeats'] ?? null,
                'max_repeats' => $section['max_repeats'] ?? null,
                'settings' => $section['settings'] ?? null,
                'deleted_at' => null,
            ])->save();
            $sectionModels[$section['key']] = $sectionModel;
        }

        $fieldModels = [];
        foreach (array_values($fields) as $index => $field) {
            $fieldModel = AssessmentFormField::withTrashed()->firstOrNew([
                'assessment_form_id' => $form->id,
                'key' => $field['key'],
            ]);

            $settings = array_diff_key($field, array_flip([
                'key',
                'label',
                'type',
                'section',
                'required',
                'rules',
                'options',
                'visible_if',
                'required_if',
                'group',
            ]));

            $fieldModel->fill([
                'assessment_form_section_id' => ($sectionModels[$field['section']] ?? null)?->id,
                'label' => $field['label'],
                'type' => $field['type'],
                'required' => (bool) ($field['required'] ?? false),
                'readonly' => $field['type'] === 'readonly',
                'sort_order' => $index + 1,
                'group' => $field['group'] ?? null,
                'validation_rules' => $field['rules'] ?? [],
                'display_logic' => $field['visible_if'] ?? null,
                'required_logic' => $field['required_if'] ?? null,
                'settings' => $settings ?: null,
                'deleted_at' => null,
            ])->save();

            $fieldModels[$field['key']] = $fieldModel;
            $this->syncOptions($fieldModel, $field['options'] ?? []);
        }

        AssessmentFormRule::query()->where('assessment_form_id', $form->id)->delete();

        foreach ($validationRules as $index => $rule) {
            $field = $fieldModels[$rule['field']] ?? null;
            AssessmentFormRule::create([
                'assessment_form_id' => $form->id,
                'assessment_form_field_id' => $field?->id,
                'type' => AssessmentFormRule::TYPE_VALIDATION,
                'name' => ($field?->label ?? $rule['field']).' validation',
                'condition' => $rule['required_if'] ?? null,
                'action' => ['rules' => $rule['rules'] ?? []],
                'message' => null,
                'severity' => 'error',
                'sort_order' => $index + 1,
            ]);
        }

        foreach ($visibilityRules as $index => $rule) {
            $field = $fieldModels[$rule['field']] ?? null;
            AssessmentFormRule::create([
                'assessment_form_id' => $form->id,
                'assessment_form_field_id' => $field?->id,
                'type' => AssessmentFormRule::TYPE_VISIBILITY,
                'name' => ($field?->label ?? $rule['field']).' visibility',
                'condition' => $rule['condition'] ?? null,
                'action' => ['effect' => 'show'],
                'severity' => 'info',
                'sort_order' => $index + 1,
            ]);
        }

        foreach ($evidenceRules as $index => $rule) {
            AssessmentFormRule::create([
                'assessment_form_id' => $form->id,
                'assessment_form_field_id' => isset($rule['field']) ? (($fieldModels[$rule['field']] ?? null)?->id) : null,
                'type' => AssessmentFormRule::TYPE_EVIDENCE,
                'name' => $rule['label'] ?? $rule['field'] ?? 'Evidence rule',
                'action' => $rule,
                'severity' => ($rule['required_on_submit'] ?? false) ? 'error' : 'info',
                'sort_order' => $index + 1,
            ]);
        }

        foreach ($signatureRules as $index => $rule) {
            AssessmentFormRule::create([
                'assessment_form_id' => $form->id,
                'assessment_form_field_id' => isset($rule['field']) ? (($fieldModels[$rule['field']] ?? null)?->id) : null,
                'type' => AssessmentFormRule::TYPE_SIGNATURE,
                'name' => $rule['field'] ?? 'Signature rule',
                'action' => $rule,
                'severity' => ($rule['required_on_submit'] ?? false) ? 'error' : 'info',
                'sort_order' => $index + 1,
            ]);
        }

        foreach ($reviewFlags as $index => $flag) {
            AssessmentFormRule::create([
                'assessment_form_id' => $form->id,
                'assessment_form_field_id' => isset($flag['field']) ? (($fieldModels[$flag['field']] ?? null)?->id) : null,
                'type' => AssessmentFormRule::TYPE_REVIEW_FLAG,
                'name' => $flag['field'] ?? 'Review flag',
                'action' => $flag,
                'message' => $flag['reason'] ?? null,
                'severity' => 'warning',
                'sort_order' => $index + 1,
                'requires_review' => true,
            ]);
        }
    }

    private function syncOptions(AssessmentFormField $field, array $options): void
    {
        $activeValues = [];
        foreach (array_values($options) as $index => $option) {
            $activeValues[] = $option['value'];
            $optionModel = AssessmentFormFieldOption::withTrashed()->firstOrNew([
                'assessment_form_field_id' => $field->id,
                'value' => $option['value'],
            ]);
            $settings = array_diff_key($option, array_flip(['label', 'value', 'score', 'needs_review']));
            $optionModel->fill([
                'label' => $option['label'],
                'score' => $option['score'] ?? null,
                'sort_order' => $index + 1,
                'requires_review' => (bool) ($option['needs_review'] ?? false),
                'settings' => $settings ?: null,
                'deleted_at' => null,
            ])->save();
        }

        $field->options()
            ->when($activeValues !== [], fn ($query) => $query->whereNotIn('value', $activeValues))
            ->when($activeValues === [], fn ($query) => $query)
            ->delete();
    }

    private function sections(): array
    {
        return [
            ['key' => 'assessment_metadata', 'label' => 'Assessment details', 'order' => 0],
            ['key' => 'personnel', 'label' => 'A. Personnel', 'order' => 1],
            ['key' => 'drugs_stocks', 'label' => 'B. Drugs and stocks', 'order' => 2],
            ['key' => 'laboratory', 'label' => 'C. Laboratory services', 'order' => 3],
            ['key' => 'other_services', 'label' => 'D. Other services', 'order' => 4],
            ['key' => 'infection_control', 'label' => 'E. General infection control', 'order' => 5],
            ['key' => 'health_information', 'label' => 'F. Health information management system', 'order' => 6],
            ['key' => 'financial_management', 'label' => 'G. Financial management system', 'order' => 7],
            ['key' => 'patient_interaction', 'label' => 'H. Patient interaction', 'order' => 8],
            ['key' => 'evidence', 'label' => 'Evidence', 'order' => 9],
            ['key' => 'signatures', 'label' => 'Signatures', 'order' => 10],
        ];
    }

    private function metadataFields(): array
    {
        return [
            $this->field('assessment_id', 'Assessment ID', 'readonly', 'assessment_metadata', true, ['string', 'max:120'], ['auto_generate' => true]),
            $this->field('assessment_date', 'Assessment date', 'date', 'assessment_metadata', true, ['date', 'before_or_equal:today']),
            $this->field('assessor_id', 'Assessor ID', 'readonly', 'assessment_metadata', true, ['integer'], ['source' => 'authenticated_user']),
            $this->field('facility_id', 'Facility ID', 'reference_select', 'assessment_metadata', true, ['integer'], ['source' => 'reference_facilities']),
            $this->field('facility_name', 'Facility name', 'readonly', 'assessment_metadata', true, ['string', 'max:255'], ['snapshot_from' => 'facility_id']),
            $this->field('lga_id', 'LGA', 'reference_select', 'assessment_metadata', true, ['integer'], ['source' => 'reference_lgas']),
            $this->field('ward_id', 'Ward', 'reference_select', 'assessment_metadata', true, ['integer'], ['source' => 'reference_wards', 'depends_on' => 'lga_id']),
            $this->field('gps_coordinates', 'GPS coordinates', 'gps', 'assessment_metadata', true, ['array'], [
                'rules' => [
                    'latitude' => ['required', 'numeric', 'between:-90,90'],
                    'longitude' => ['required', 'numeric', 'between:-180,180'],
                    'accuracy_meters' => ['nullable', 'numeric', 'min:0'],
                ],
            ]),
        ];
    }

    private function personnelFields(): array
    {
        return collect([
            ['doctors_available', 'Does the facility have doctors?', 'total_doctors', 'Total number of doctors'],
            ['nurses_midwives_available', 'Are nurses / midwives available?', 'total_nurses_midwives', 'Total number of nurses / midwives'],
            ['cho_chew_jchew_available', 'Are CHO / CHEW / JCHEW available?', 'total_cho_chew_jchew', 'Total number of CHO / CHEW / JCHEW'],
            ['pharm_tech_dispenser_available', 'Is a Pharm. Tech / drug dispenser available?', 'total_pharm_tech_dispenser', 'Total number of Pharm. Tech / drug dispensers'],
            ['record_officer_available', 'Is a record officer available?', 'total_record_officers', 'Total number of record officers'],
            ['laboratory_scientist_available', 'Is a laboratory scientist available?', 'total_laboratory_scientists', 'Total number of laboratory scientists'],
            ['other_personnel_available', 'Are other personnel available?', 'total_other_personnel', 'Total number of other personnel'],
        ])->flatMap(fn ($item) => $this->yesNoWithCount(...$item, section: 'personnel'))->values()->all();
    }

    private function drugAndStockFields(): array
    {
        $fields = [
            $this->select('drug_supply_source', 'Main source of supply', 'drugs_stocks', true, [
                ['label' => 'DMA', 'value' => 'dma', 'score' => 2],
                ['label' => 'NGO supply', 'value' => 'ngo_supply', 'score' => 1],
                ['label' => 'Local purchase', 'value' => 'local_purchase', 'score' => 1],
            ]),
        ];

        $standardBands = [
            ['label' => '>20', 'value' => 'gt_20', 'score' => 2],
            ['label' => '>10 but <20', 'value' => 'gt_10_lt_20', 'score' => 1.5],
            ['label' => '<10', 'value' => 'lt_10', 'score' => 1],
        ];
        $emergencyBands = [
            ['label' => '>10', 'value' => 'gt_10', 'score' => 2],
            ['label' => '>5 but <10', 'value' => 'gt_5_lt_10', 'score' => 1.5],
            ['label' => '<5', 'value' => 'lt_5', 'score' => 1],
        ];
        $maternityBands = [
            ['label' => '>5', 'value' => 'gt_5', 'score' => 2],
            ['label' => 'Ambiguous middle band from source checklist', 'value' => 'source_middle_band', 'score' => 1.5, 'needs_review' => true],
            ['label' => '<2', 'value' => 'lt_2', 'score' => 1],
        ];
        $routineBands = [
            ['label' => '>1 tin', 'value' => 'gt_1_tin', 'score' => 2],
            ['label' => '<1 but > half tin', 'value' => 'lt_1_gt_half_tin', 'score' => 1.5],
            ['label' => 'Countable', 'value' => 'countable', 'score' => 1],
        ];

        $groups = [
            'Antibiotics' => [
                ['amoxicillin', 'Amoxicillin (injection / capsules / suspension)', $standardBands],
                ['ciprofloxacin', 'Ciprofloxacin (injection / tablet)', $standardBands],
                ['ampiclox', 'Ampiclox (injection / capsules / suspension)', $standardBands],
                ['metronidazole', 'Metronidazole (injection / tablet / suspension)', $standardBands],
            ],
            'NSAIDs' => [
                ['paracetamol', 'Paracetamol (injection / tablet / syrup)', $standardBands],
                ['ibuprofen', 'Ibuprofen (tablet / suspension)', $standardBands],
            ],
            'Antimalaria' => [
                ['act', 'ACT tablets (tablet / suspension / injection)', $standardBands],
                ['sulfadoxine_pyrimethamine', 'Sulfadoxine + pyrimethamine (suspension / tablets)', $standardBands],
            ],
            'Emergency drugs' => [
                ['salbutamol', 'Salbutamol (nebules / tablet / syrup)', $emergencyBands],
                ['adrenaline', 'Injection adrenaline', $emergencyBands],
                ['hydrocortisone', 'Injection hydrocortisone', $emergencyBands],
                ['dextrose_50', '50% dextrose', $emergencyBands],
            ],
            'Antihelminths drugs' => [
                ['albendazole_mebendazole', 'Albendazole / mebendazole', $standardBands],
            ],
            'Maternity drugs' => [
                ['oxytocin', 'Injection oxytocin', $maternityBands],
                ['magnesium_sulfate', 'Injection magnesium sulfate', $maternityBands],
                ['misoprostol', 'Misoprostol', $maternityBands],
            ],
            'Routine drugs' => [
                ['folic_acid', 'Folic acid', $routineBands],
                ['ferrous_sulfate', 'Ferrous sulfate', $routineBands],
            ],
        ];

        foreach ($groups as $group => $items) {
            foreach ($items as [$key, $label, $bands]) {
                $fields = array_merge($fields, $this->stockItem($key, $label, $group, $bands));
            }
        }

        $fields = array_merge($fields, [
            ...$this->stockItem('syringe_needles', 'Syringe and needles', 'Consumables and antiseptics', [
                ['label' => '>2 boxes', 'value' => 'gt_2_boxes', 'score' => 2],
                ['label' => '<2 but >1 boxes', 'value' => 'lt_2_gt_1_boxes', 'score' => 1.5],
                ['label' => '>5 rolls', 'value' => 'gt_5_rolls', 'score' => 1, 'needs_review' => true],
            ]),
            ...$this->stockItem('methylated_spirit', 'Methylated spirit', 'Consumables and antiseptics', [
                ['label' => '>10 bottles', 'value' => 'gt_10_bottles', 'score' => 2],
                ['label' => '<10 but >5 bottles', 'value' => 'lt_10_gt_5_bottles', 'score' => 1.5],
                ['label' => '>3 bottles', 'value' => 'gt_3_bottles', 'score' => 1],
            ]),
            ...$this->stockItem('iodine_tincture', 'Iodine tincture', 'Consumables and antiseptics', [
                ['label' => '>10 bottles', 'value' => 'gt_10_bottles', 'score' => 2],
                ['label' => '<10 but >5 bottles', 'value' => 'lt_10_gt_5_bottles', 'score' => 1.5],
                ['label' => '>3 bottles', 'value' => 'gt_3_bottles', 'score' => 1],
            ]),
            ...$this->stockItem('cotton_wool', 'Cotton wool', 'Consumables and antiseptics', [
                ['label' => '>10 bottles', 'value' => 'gt_10_bottles', 'score' => 2, 'needs_review' => true],
                ['label' => '<10 but >3 bottles', 'value' => 'lt_10_gt_3_bottles', 'score' => 1.5, 'needs_review' => true],
                ['label' => '>3 bottles', 'value' => 'gt_3_bottles', 'score' => 1, 'needs_review' => true],
            ]),
            ...$this->stockItem('hand_gloves', 'Hand gloves', 'Consumables and antiseptics', [
                ['label' => '>20 pairs', 'value' => 'gt_20_pairs', 'score' => 2],
                ['label' => '<10 but >5 pairs', 'value' => 'lt_10_gt_5_pairs', 'score' => 1.5],
                ['label' => '<5 pairs', 'value' => 'lt_5_pairs', 'score' => 1],
            ]),
            ...$this->stockItem('plaster', 'Plaster', 'Consumables and antiseptics', [
                ['label' => 'Source options ambiguous', 'value' => 'source_ambiguous', 'score' => null, 'needs_review' => true],
            ], noOptionPrinted: true),
            ...$this->stockItem('cannula_butterfly_needles', 'Cannula / butterfly needles', 'Consumables and antiseptics', [
                ['label' => 'Same as plaster in source checklist', 'value' => 'same_as_plaster', 'score' => null, 'needs_review' => true],
            ]),
            ...$this->stockItem('fluid_blood_giving_sets', 'Fluid and blood giving sets', 'Consumables and antiseptics', [
                ['label' => 'Same as plaster in source checklist', 'value' => 'same_as_plaster', 'score' => null, 'needs_review' => true],
            ]),
        ]);

        foreach ([
            ['normal_saline', 'Normal saline (1 litre / half litre)', 2, 0],
            ['dextrose_saline', 'Dextrose saline', 2, 0],
            ['insecticide_treated_net', 'Insecticide treated net', 2, 0],
            ['oral_rehydrating_salt', 'Oral rehydrating salt', 2, 2, true],
            ['other_drugs_stocks', 'Others', 2, 0],
        ] as $item) {
            [$key, $label, $yesScore, $noScore] = $item;
            $needsReview = (bool) ($item[4] ?? false);
            $fields[] = $this->yesNo($key, $label, 'drugs_stocks', $yesScore, $noScore, ['needs_review' => (bool) ($needsReview ?? false)]);
        }

        return $fields;
    }

    private function laboratoryFields(): array
    {
        return collect([
            ['microscope_available', 'Does the facility have a microscope?'],
            ['centrifuge_available', 'Does the facility have a centrifuge machine?'],
            ['malaria_rdt_available', 'Does the facility have rapid diagnostic test kit (malaria)?'],
            ['pregnancy_rdt_available', 'Does the facility have rapid diagnostic test kit (pregnancy)?'],
            ['hiv_hepatitis_rdt_available', 'Does the facility have rapid diagnostic test kit (HIV, hepatitis etc)?'],
            ['test_reagents_available', 'Does the facility have test reagents?'],
            ['laboratory_sop_available', 'Does the facility have standard operation procedure in laboratory?'],
        ])->map(fn ($item) => $this->yesNo($item[0], $item[1], 'laboratory'))->all();
    }

    private function otherServicesFields(): array
    {
        return [
            $this->yesNo('delivery_protocol_available', 'Does the facility have delivery protocol?', 'other_services'),
            $this->yesNo('conducts_immunization', 'Does the facility conduct immunization?', 'other_services'),
            $this->yesNo('measles_immunization_available', 'Measles immunization', 'other_services', 1, 0, ['visible_if' => ['field' => 'conducts_immunization', 'operator' => 'equals', 'value' => 'yes']]),
            $this->yesNo('diphtheria_immunization_available', 'Diphtheria', 'other_services', 1, 0, ['visible_if' => ['field' => 'conducts_immunization', 'operator' => 'equals', 'value' => 'yes']]),
            $this->yesNo('bcg_immunization_available', 'BCG (Bacille Calmette Guerin)', 'other_services', 1, 0, ['visible_if' => ['field' => 'conducts_immunization', 'operator' => 'equals', 'value' => 'yes']]),
            $this->yesNo('opv_immunization_available', 'OPV (Oral Polio Vaccine)', 'other_services', 1, 0, ['visible_if' => ['field' => 'conducts_immunization', 'operator' => 'equals', 'value' => 'yes']]),
            $this->yesNo('other_immunization_available', 'Other immunization', 'other_services', 1, 0, ['visible_if' => ['field' => 'conducts_immunization', 'operator' => 'equals', 'value' => 'yes']]),
        ];
    }

    private function infectionControlFields(): array
    {
        return [
            $this->select('water_source', 'Source of water of the facility', 'infection_control', true, [
                ['label' => 'Borehole / well / tap water', 'value' => 'onsite_water', 'score' => 2],
                ['label' => 'Nearby well / borehole / tap water', 'value' => 'nearby_water', 'score' => 1],
                ['label' => 'No source of water', 'value' => 'none', 'score' => 0],
            ]),
            $this->yesNo('proper_hand_washing_culture', 'Does the facility adhere to proper hand washing culture?', 'infection_control'),
            $this->select('waste_management_method', 'How does the facility manage waste system?', 'infection_control', true, [
                ['label' => 'Waste bin / landfill / burning', 'value' => 'managed', 'score' => 2],
                ['label' => 'None', 'value' => 'none', 'score' => 0],
            ]),
            $this->yesNo('safety_boxes_available', 'Does the facility have safety boxes?', 'infection_control'),
            $this->select('facility_cleanliness', 'How clean is the facility?', 'infection_control', true, [
                ['label' => 'Very clean', 'value' => 'very_clean', 'score' => 2],
                ['label' => 'Averagely clean', 'value' => 'average', 'score' => 1],
                ['label' => 'Dirty', 'value' => 'dirty', 'score' => 0],
            ]),
            $this->select('equipment_sterilization_method', 'Method of equipment sterilization', 'infection_control', true, [
                ['label' => 'Burning', 'value' => 'burning', 'score' => 2],
                ['label' => 'Use of chemical', 'value' => 'chemical', 'score' => 1],
                ['label' => 'None', 'value' => 'none', 'score' => 0],
            ]),
        ];
    }

    private function healthInformationFields(): array
    {
        return [
            $this->yesNo('beneficiary_list_available', 'Does the facility have updated beneficiary list / form?', 'health_information'),
            $this->yesNo('patient_referral_form_available', 'Does the facility have patient referral form?', 'health_information'),
            $this->select('referral_form_documentation_quality', 'How well is the referral form documented?', 'health_information', true, [
                ['label' => 'Well documented', 'value' => 'well_documented', 'score' => 2],
                ['label' => 'Poorly documented', 'value' => 'poorly_documented', 'score' => 0],
            ]),
            $this->yesNo('management_staff_available', 'Does the facility have a management staff?', 'health_information'),
            $this->yesNo('functional_ict_system', 'Does the facility have a functional ICT system?', 'health_information'),
        ];
    }

    private function financialManagementFields(): array
    {
        return [
            $this->yesNo('received_appropriate_capitation_fee', 'Does the facility receive the appropriate capitation fee?', 'financial_management', null, null),
            $this->field('previous_month_capitation_amount', 'Amount received by facility in the previous month', 'currency', 'financial_management', true, ['numeric', 'min:0']),
            $this->yesNo('approved_capitation_appropriation_formula_followed', 'Does the facility follow the approved method of capitation appropriation formula?', 'financial_management', null, null),
            $this->yesNo('official_bank_account_available', 'Does the facility have official bank account?', 'financial_management', null, null),
            $this->field('bank_name', 'Name of bank', 'text', 'financial_management', false, ['string', 'max:255'], ['visible_if' => ['field' => 'official_bank_account_available', 'operator' => 'equals', 'value' => 'yes'], 'required_if' => ['official_bank_account_available' => 'yes']]),
            $this->field('bank_account_number', 'Bank account number', 'text', 'financial_management', false, ['string', 'max:30'], ['visible_if' => ['field' => 'official_bank_account_available', 'operator' => 'equals', 'value' => 'yes'], 'required_if' => ['official_bank_account_available' => 'yes']]),
        ];
    }

    private function patientInteractionFields(): array
    {
        return [
            $this->select('worker_professionalism', 'How can you describe the professionalism of the workers?', 'patient_interaction', true, [
                ['label' => 'Excellent', 'value' => 'excellent', 'score' => 2],
                ['label' => 'Moderate', 'value' => 'moderate', 'score' => 1],
                ['label' => 'Poor', 'value' => 'poor', 'score' => 0],
            ]),
            $this->select('waiting_time', 'How long does it take before you are attended to?', 'patient_interaction', true, [
                ['label' => '<30 minutes', 'value' => 'lt_30_minutes', 'score' => 2],
                ['label' => '>30 minutes but <2 hours', 'value' => 'gt_30_lt_2_hours', 'score' => 1],
                ['label' => '>2 hours', 'value' => 'gt_2_hours', 'score' => 1],
            ]),
            $this->yesNo('discriminates_against_nicare_enrollee', 'Does the personnel discriminate against NICARE enrollee?', 'patient_interaction', 0, 2),
            $this->yesNo('satisfied_with_service_care', 'Are you satisfied with the overall service care in the facility?', 'patient_interaction'),
            $this->field('service_dissatisfaction_reason', 'Reason for dissatisfaction', 'textarea', 'patient_interaction', false, ['string'], ['visible_if' => ['field' => 'satisfied_with_service_care', 'operator' => 'equals', 'value' => 'no'], 'required_if' => ['satisfied_with_service_care' => 'no']]),
        ];
    }

    private function evidenceFields(): array
    {
        return [
            $this->field('facility_photos', 'Facility photos', 'photo_collection', 'evidence', false, ['array'], ['min_items' => 0, 'max_items' => 20]),
            $this->field('supporting_documents', 'Supporting documents', 'file_collection', 'evidence', false, ['array'], ['accepted_mime_types' => ['image/jpeg', 'image/png', 'application/pdf'], 'max_file_size_mb' => 5]),
        ];
    }

    private function signatureFields(): array
    {
        return [
            $this->field('facility_representative_name', 'Facility representative name', 'text', 'signatures', true, ['string', 'max:255']),
            $this->field('facility_representative_signature', 'Facility representative signature', 'signature', 'signatures', true, ['string']),
            $this->field('assessor_signature', 'Assessor signature', 'signature', 'signatures', true, ['string']),
            $this->field('accuracy_attestation', 'I confirm that the information collected is accurate', 'checkbox', 'signatures', true, ['accepted']),
        ];
    }

    private function yesNoWithCount(string $key, string $label, string $countKey, string $countLabel, string $section): array
    {
        return [
            $this->field($key, $label, 'yes_no_with_count', $section, true, ['array'], [
                'count_key' => $countKey,
                'count_label' => $countLabel,
                'count_required_value' => 'yes',
                'count_rules' => ['integer', 'min:1'],
                'count_minimum' => 1,
                'options' => [
                    ['label' => 'Yes', 'value' => 'yes', 'score' => 2],
                    ['label' => 'No', 'value' => 'no', 'score' => 0],
                ],
            ]),
        ];
    }

    private function stockItem(string $key, string $label, string $group, array $bands, bool $noOptionPrinted = false): array
    {
        return [
            $this->yesNo($key.'_available', $label.' available?', 'drugs_stocks', 0, 0, [
                'group' => $group,
                'no_option_printed_in_source' => $noOptionPrinted,
            ]),
            $this->select($key.'_quantity_band', $label.' quantity available', 'drugs_stocks', false, $bands, [
                'group' => $group,
                'visible_if' => ['field' => $key.'_available', 'operator' => 'equals', 'value' => 'yes'],
                'required_if' => [$key.'_available' => 'yes'],
            ]),
        ];
    }

    private function yesNo(string $key, string $label, string $section, int|float|null $yesScore = 2, int|float|null $noScore = 0, array $extra = []): array
    {
        return $this->field($key, $label, 'yes_no', $section, true, ['in:yes,no'], $extra + [
            'options' => [
                ['label' => 'Yes', 'value' => 'yes', 'score' => $yesScore],
                ['label' => 'No', 'value' => 'no', 'score' => $noScore],
            ],
        ]);
    }

    private function select(string $key, string $label, string $section, bool $required, array $options, array $extra = []): array
    {
        return $this->field($key, $label, 'select', $section, $required, ['string'], $extra + ['options' => $options]);
    }

    private function field(string $key, string $label, string $type, string $section, bool $required, array $rules = [], array $extra = []): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'type' => $type,
            'section' => $section,
            'required' => $required,
            'rules' => $rules,
        ] + $extra;
    }

    private function validationRules(array $fields): array
    {
        return collect($fields)->map(function (array $field): array {
            $rules = $field['rules'] ?? [];
            array_unshift($rules, ($field['required'] ?? false) ? 'required' : 'nullable');

            return [
                'field' => $field['key'],
                'rules' => array_values(array_unique($rules)),
                'required_if' => $field['required_if'] ?? null,
            ];
        })->values()->all();
    }

    private function visibilityRules(array $fields): array
    {
        return collect($fields)
            ->filter(fn (array $field) => isset($field['visible_if']))
            ->map(fn (array $field) => ['field' => $field['key'], 'condition' => $field['visible_if']])
            ->values()
            ->all();
    }

    private function scoringRules(array $fields): array
    {
        return collect($fields)
            ->filter(fn (array $field) => isset($field['options']))
            ->map(fn (array $field) => [
                'field' => $field['key'],
                'type' => 'option_score',
                'options' => collect($field['options'])
                    ->map(fn (array $option) => [
                        'value' => $option['value'],
                        'score' => $option['score'] ?? null,
                        'needs_review' => (bool) ($option['needs_review'] ?? $field['needs_review'] ?? false),
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    private function maxScore(array $scoringRules): float
    {
        return collect($scoringRules)
            ->sum(fn (array $rule) => collect($rule['options'] ?? [])->max('score') ?? 0);
    }

    private function requiredEvidenceRules(): array
    {
        return [
            ['field' => 'facility_photos', 'required_on_submit' => false, 'label' => 'Facility photos'],
            ['field' => 'supporting_documents', 'required_on_submit' => false, 'label' => 'Supporting documents'],
            ['field' => 'gps_coordinates', 'required_on_submit' => true, 'label' => 'GPS location'],
        ];
    }

    private function signatureRules(): array
    {
        return [
            ['field' => 'facility_representative_signature', 'required_on_submit' => true],
            ['field' => 'assessor_signature', 'required_on_submit' => true],
            ['field' => 'accuracy_attestation', 'required_on_submit' => true],
        ];
    }

    private function reviewFlags(): array
    {
        return [
            ['field' => 'plaster_quantity_band', 'reason' => 'Source checklist has malformed plaster quantity options and no printed NO option.'],
            ['field' => 'oral_rehydrating_salt', 'reason' => 'Source checklist says YES (2) / NO (2); confirm whether NO should be 0.'],
            ['field' => 'oxytocin_quantity_band', 'reason' => 'Maternity middle band appears inconsistent in source checklist.'],
            ['field' => 'magnesium_sulfate_quantity_band', 'reason' => 'Maternity middle band appears inconsistent in source checklist.'],
            ['field' => 'misoprostol_quantity_band', 'reason' => 'Maternity middle band appears inconsistent in source checklist.'],
            ['field' => 'cotton_wool_quantity_band', 'reason' => 'Source checklist uses bottle units for cotton wool; confirm intended units.'],
        ];
    }
}
