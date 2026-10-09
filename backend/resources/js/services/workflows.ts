export type ReviewStatus = 'Submitted' | 'In review' | 'Returned' | 'Approved';

export type SubmissionReview = {
  id: string;
  facility: string;
  form: string;
  submitted: string;
  assessor: string;
  score: string;
  status: ReviewStatus;
  tone: 'green' | 'amber' | 'blue' | 'red';
};

export type FacilityAssessment = {
  id: string;
  facility: string;
  lga: string;
  stage: string;
  nextAction: string;
  owner: string;
  tone: 'green' | 'amber' | 'blue' | 'slate';
};

export type SyncDevice = {
  id: string;
  device: string;
  officer: string;
  lastSync: string;
  pendingUploads: number;
  status: string;
  tone: 'green' | 'amber' | 'red';
};

export const reviewQueue: SubmissionReview[] = [
  { id: 'SUB-1024', facility: 'Bose PHC', form: 'NiCare QA Checklist', submitted: '12 Jan 2025, 10:24 AM', assessor: 'Sadiya Idris', score: '87%', status: 'Submitted', tone: 'green' },
  { id: 'SUB-1023', facility: 'Chanchaga PHC', form: 'Facility follow-up', submitted: '11 Jan 2025, 4:18 PM', assessor: 'Amina Yusuf', score: '72%', status: 'In review', tone: 'amber' },
  { id: 'SUB-1022', facility: 'Makunkele PHC', form: 'Infection control', submitted: '10 Jan 2025, 8:02 AM', assessor: 'Musa Danladi', score: '61%', status: 'Returned', tone: 'red' },
];

export const facilityAssessments: FacilityAssessment[] = [
  { id: 'FAC-001', facility: 'Bose PHC', lga: 'Rano', stage: 'Scheduled', nextAction: 'Assign assessor', owner: 'QA Desk', tone: 'blue' },
  { id: 'FAC-002', facility: 'Chanchaga PHC', lga: 'Chanchaga', stage: 'In field review', nextAction: 'Sync mobile submission', owner: 'Sadiya Idris', tone: 'amber' },
  { id: 'FAC-003', facility: 'Makunkele PHC', lga: 'Bosso', stage: 'Reviewed', nextAction: 'Publish QA outcome', owner: 'Amina Yusuf', tone: 'green' },
];

export const syncDevices: SyncDevice[] = [
  { id: 'DEV-01', device: 'ODK Collect - Amina', officer: 'Amina Yusuf', lastSync: 'Today, 09:15 AM', pendingUploads: 0, status: 'Synced', tone: 'green' },
  { id: 'DEV-02', device: 'ODK Collect - Sadiya', officer: 'Sadiya Idris', lastSync: 'Today, 07:42 AM', pendingUploads: 4, status: 'Pending upload', tone: 'amber' },
  { id: 'DEV-03', device: 'ODK Collect - Musa', officer: 'Musa Danladi', lastSync: 'Yesterday, 05:10 PM', pendingUploads: 2, status: 'Needs attention', tone: 'red' },
];
