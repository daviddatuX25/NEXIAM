<script lang="ts">
    import IntakeCorrectionFields from '../../../resources/js/Pages/Admission/IntakeCorrectionFields.svelte';

    let form = $state({
        applicant_type: 'transferee',
        strand: 'Previously saved course',
        last_school_enrolled: '',
        contact_email: '',
        contact_phone: '',
        address_line: '',
        city: '',
        province: '',
        zip_code: '',
        program_preference_1_code: '',
        program_preference_2_code: '',
        program_preference_3_code: '',
        errors: {},
    });
    const intake_suggestions = {
        schools: [],
        shs: [],
        programs: [
            { key: 'bsit', label: 'Bachelor of Science in Information Technology', aliases: ['BSIT'] },
        ],
    };
</script>

<IntakeCorrectionFields {form} {intake_suggestions} />
<output data-testid="form-strand">{form.strand}</output>
