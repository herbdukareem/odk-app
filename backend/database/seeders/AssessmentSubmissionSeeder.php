<?php

namespace Database\Seeders;

use App\Models\AssessmentSubmission;
use App\Services\Submissions\AssessmentSubmissionService;
use Illuminate\Database\Seeder;

class AssessmentSubmissionSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(AssessmentSubmissionService::class);

        foreach ($this->samples() as $sample) {
            $service->submit($sample);
        }
    }

    private function samples(): array
    {
        $baseAnswers = [
            'assessment_id' => 'NIC-ASS-SEED',
            'assessment_date' => now()->toDateString(),
            'assessor_id' => 'QA-001',
            'facility_id' => 'FAC-SEED',
            'facility_name' => 'Bosso PHC',
            'lga_id' => 1,
            'ward_id' => 1,
            'gps_coordinates' => ['latitude' => 9.6502, 'longitude' => 6.5263, 'accuracy_meters' => 8],
            'doctors_available' => 'yes',
            'total_doctors' => 2,
            'nurses_midwives_available' => 'yes',
            'total_nurses_midwives' => 4,
            'cho_chew_jchew_available' => 'yes',
            'total_cho_chew_jchew' => 6,
            'drug_supply_source' => 'dma',
            'microscope_available' => 'yes',
            'centrifuge_available' => 'no',
            'delivery_protocol_available' => 'yes',
            'conducts_immunization' => 'yes',
            'measles_immunization_available' => 'yes',
            'water_source' => 'onsite_water',
            'proper_hand_washing_culture' => 'yes',
            'beneficiary_list_available' => 'yes',
            'patient_referral_form_available' => 'yes',
            'received_appropriate_capitation_fee' => 'yes',
            'previous_month_capitation_amount' => 250000,
            'approved_capitation_appropriation_formula_followed' => 'yes',
            'official_bank_account_available' => 'yes',
            'bank_name' => 'First Bank of Nigeria',
            'bank_account_number' => '0000123412',
            'worker_professionalism' => 'excellent',
            'waiting_time' => 'lt_30_minutes',
            'discriminates_against_nicare_enrollee' => 'no',
            'satisfied_with_service_care' => 'yes',
            'facility_representative_name' => 'Aliyu Bello',
            'facility_representative_signature' => 'signature://facility-rep',
            'assessor_signature' => 'signature://assessor',
            'accuracy_attestation' => true,
        ];

        return [
            [
                'client_submission_id' => 'seed-mobile-bosso-phc',
                'form_slug' => 'nicare-qa-checklist',
                'assessment_id' => 'NIC-ASS-20260112-00001',
                'facility' => ['id' => 'NG-BOS-001', 'name' => 'Bosso PHC', 'lga' => 'Bosso', 'ward' => 'Maikunkele Ward', 'type' => 'Primary Health Care Centre'],
                'assessor' => ['id' => 'QA-001', 'name' => 'Amina Yusuf'],
                'assessment_date' => '2026-01-12',
                'submitted_at' => '2026-01-12 10:24:00',
                'status' => AssessmentSubmission::STATUS_SUBMITTED,
                'answers' => $baseAnswers,
                'gps' => ['latitude' => 9.6502, 'longitude' => 6.5263, 'accuracy_meters' => 8],
                'attachments' => [
                    ['field_key' => 'facility_photos', 'type' => 'photo', 'path' => 'evidence/bosso/front-view.jpg', 'original_name' => 'front-view.jpg', 'mime_type' => 'image/jpeg'],
                    ['field_key' => 'supporting_documents', 'type' => 'document', 'path' => 'evidence/bosso/referral-form.pdf', 'original_name' => 'referral-form.pdf', 'mime_type' => 'application/pdf'],
                ],
            ],
            [
                'client_submission_id' => 'seed-mobile-chanchaga-phc',
                'form_slug' => 'nicare-qa-checklist',
                'assessment_id' => 'NIC-ASS-20260111-00002',
                'facility' => ['id' => 'NG-CHA-002', 'name' => 'Chanchaga PHC', 'lga' => 'Chanchaga', 'ward' => 'Tungan Maje', 'type' => 'Primary Health Care Centre'],
                'assessor' => ['id' => 'QA-002', 'name' => 'Musa Bello'],
                'assessment_date' => '2026-01-11',
                'submitted_at' => '2026-01-11 16:18:00',
                'status' => AssessmentSubmission::STATUS_UNDER_REVIEW,
                'answers' => [...$baseAnswers, 'facility_name' => 'Chanchaga PHC', 'doctors_available' => 'no'],
                'gps' => ['latitude' => 9.6124, 'longitude' => 6.5489, 'accuracy_meters' => 12],
            ],
            [
                'client_submission_id' => 'seed-mobile-maikunkele-phc',
                'form_slug' => 'nicare-qa-checklist',
                'assessment_id' => 'NIC-ASS-20260110-00003',
                'facility' => ['id' => 'NG-MAI-003', 'name' => 'Maikunkele PHC', 'lga' => 'Bosso', 'ward' => 'Maikunkele Ward', 'type' => 'Primary Health Care Centre'],
                'assessor' => ['id' => 'QA-003', 'name' => 'Zainab Ahmed'],
                'assessment_date' => '2026-01-10',
                'submitted_at' => '2026-01-10 09:02:00',
                'status' => AssessmentSubmission::STATUS_RETURNED,
                'answers' => [...$baseAnswers, 'facility_name' => 'Maikunkele PHC', 'satisfied_with_service_care' => 'no', 'service_dissatisfaction_reason' => 'Long queue at pharmacy'],
                'gps' => ['latitude' => 9.6921, 'longitude' => 6.5004, 'accuracy_meters' => 15],
            ],
        ];
    }
}
