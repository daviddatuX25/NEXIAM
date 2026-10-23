<script>
    import ApplicantLayout from '@/Pages/Applicant/ApplicantLayout.svelte';
    import OfficeInquiryWorkspace from '@/Components/Admission/OfficeInquiryWorkspace.svelte';

    let props = $props();
</script>

<ApplicantLayout title="Office inquiries" wide={true} breadcrumbs={[{ label: 'Office inquiries' }]}>
    <OfficeInquiryWorkspace {...props} />
</ApplicantLayout>
