export function groupApplicantDestinations(destinations) {
    const groups = Object.create(null);
    for (const destination of destinations) {
        const group = destination.group || 'Applicant';
        (groups[group] ??= []).push(destination);
    }
    return Object.entries(groups);
}
