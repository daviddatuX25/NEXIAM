<?php

namespace Database\Seeders;

use App\Models\PrivacyPolicy;
use Illuminate\Database\Seeder;

class PrivacyPolicySeeder extends Seeder
{
    public function run(): void
    {
        if (PrivacyPolicy::exists()) {
            return;
        }

        PrivacyPolicy::create([
            'title' => 'Privacy Policy',
            'content' => <<<'POLICY'
DATA PRIVACY NOTICE

This institution is committed to protecting the privacy and security of your personal information. This Privacy Policy explains how we collect, use, store, and safeguard data in accordance with the Data Privacy Act of 2012 (Republic Act No. 10173) and its Implementing Rules and Regulations.

1. INFORMATION WE COLLECT

When you submit an application for admission, we collect the following personal information:
• Full name (first name, middle name, last name, suffix)
• Date of birth
• Sex
• Email address
• Phone number (optional)
• Home address (optional)
• General Weighted Average (GWA)
• Course preferences

2. PURPOSE OF COLLECTION

Your personal information is collected and processed for the following purposes:
• Processing and evaluating your application for admission
• Scheduling and administering entrance examinations
• Communicating important updates regarding your application status
• Generating admission documents such as examination permits and result sheets
• Statistical and research purposes (in anonymized or aggregated form)

3. HOW WE USE YOUR DATA

Your data will be used solely for admission-related processes. We do not sell, trade, or rent your personal information to third parties. Access to your data is limited to authorized personnel involved in the admissions process.

4. DATA STORAGE AND SECURITY

We implement appropriate technical and organizational measures to protect your personal data against unauthorized access, alteration, disclosure, or destruction. Your data is stored in secured systems with restricted access controls.

5. DATA RETENTION

Your personal information will be retained for the duration necessary to fulfill the purposes outlined in this notice. After the retention period, your data will be securely disposed of in accordance with institutional policies.

6. YOUR RIGHTS

Under the Data Privacy Act of 2012, you have the following rights:
• Right to be informed about the collection and processing of your data
• Right to access your personal information
• Right to correct inaccurate or incomplete data
• Right to object to the processing of your data
• Right to erasure or blocking of data under certain conditions
• Right to file a complaint with the National Privacy Commission

7. CONSENT

By submitting your application and checking the consent box, you acknowledge that you have read and understood this Privacy Policy and consent to the collection, use, and processing of your personal data as described herein.

8. CONTACT INFORMATION

For questions or concerns regarding this Privacy Policy or the handling of your personal data, please contact the Office of the Registrar.

This policy may be updated from time to time. Any changes will be reflected on this page.
POLICY,
            'is_active' => true,
        ]);
    }
}
