<script>
    import ApplicantLayout from '@/Pages/Applicant/ApplicantLayout.svelte';
    import OfficeInquiryWorkspace from '@/Components/Admission/OfficeInquiryWorkspace.svelte';

    let props = $props();
</script>

<ApplicantLayout title="Office inquiry" wide={true} breadcrumbs={[{ label: 'Office inquiries', href: props.return_url ?? props.urls?.return ?? props.urls?.index }, { label: 'Conversation' }]}>
    <OfficeInquiryWorkspace {...props} />
</ApplicantLayout>
