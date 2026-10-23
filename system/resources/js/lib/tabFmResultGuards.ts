export function hasValidTabFmNeuralProvenance(result: unknown): boolean {
    if (result === null || typeof result !== 'object' || Array.isArray(result)) {
        return false;
    }

    const model = result as Record<string, unknown>;
    const modalityCoverage = model.modality_coverage;
    if (modalityCoverage === null || typeof modalityCoverage !== 'object' || Array.isArray(modalityCoverage)) {
        return false;
    }

    const provenance = (modalityCoverage as Record<string, unknown>).inference_provenance;
    if (provenance === null || typeof provenance !== 'object' || Array.isArray(provenance)) {
        return false;
    }

    const inferenceProvenance = provenance as Record<string, unknown>;
    return model.inference_backend === 'tabpfn_pytorch'
        && Object.prototype.hasOwnProperty.call(model, 'fallback_reason')
        && model.fallback_reason === null
        && inferenceProvenance.package === 'tabpfn'
        && typeof inferenceProvenance.package_version === 'string'
        && inferenceProvenance.package_version.trim() !== ''
        && inferenceProvenance.package_version === model.engine_version
        && typeof inferenceProvenance.checkpoint_identity === 'string'
        && inferenceProvenance.checkpoint_identity.trim() !== ''
        && typeof inferenceProvenance.checkpoint_sha256 === 'string'
        && /^[a-f0-9]{64}$/.test(inferenceProvenance.checkpoint_sha256);
}
