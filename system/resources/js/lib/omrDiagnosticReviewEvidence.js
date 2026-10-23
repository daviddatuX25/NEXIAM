export function diagnosticRecord(value) {
        return value !== null &&
            typeof value === 'object' &&
            !Array.isArray(value)
            ? value
            : {};
    }

    export function diagnosticArray(value) {
        return Array.isArray(value) ? value : [];
    }

    export function diagnosticText(value, fallback = null) {
        return typeof value === 'string' && value.trim() !== ''
            ? value.trim()
            : fallback;
    }

    export function diagnosticCode(value) {
        if (typeof value !== 'string' && typeof value !== 'number') {
            return null;
        }

        const code = String(value).trim();

        return code === '' ? null : code;
    }

    export function diagnosticNumber(value, fallback = null) {
        if (typeof value === 'number' && Number.isFinite(value)) {
            return value;
        }

        if (typeof value === 'string' && value.trim() !== '') {
            const parsed = Number(value);

            return Number.isFinite(parsed) ? parsed : fallback;
        }

        return fallback;
    }

    export function diagnosticInteger(value, fallback = null) {
        const parsed = diagnosticNumber(value, null);

        return parsed !== null && Number.isSafeInteger(parsed) && parsed >= 0
            ? parsed
            : fallback;
    }

    export function diagnosticPositiveInteger(value, fallback = null) {
        const parsed = diagnosticInteger(value, null);

        return parsed !== null && parsed > 0 ? parsed : fallback;
    }

    export function diagnosticFirstArray(...values) {
        const first = values.find(
            (value) => Array.isArray(value) && value.length > 0,
        );

        if (first) return first;

        return values.find((value) => Array.isArray(value)) ?? [];
    }

    export function diagnosticUniqueCodes(value) {
        const seen = new Set();
        const codes = [];

        for (const entry of diagnosticArray(value)) {
            const code = diagnosticCode(
                diagnosticRecord(entry).code ??
                    diagnosticRecord(entry).option ??
                    entry,
            );
            if (code === null || seen.has(code)) continue;
            seen.add(code);
            codes.push(code);
        }

        return codes;
    }

    function diagnosticState(value) {
        const state = diagnosticText(value)?.toLowerCase() ?? null;

        return (
            {
                match: 'marked',
                selected: 'marked',
                exact: 'marked',
                multiple: 'ambiguous',
                multi_mark: 'ambiguous',
            }[state] ??
            ([
                'marked',
                'blank',
                'ambiguous',
                'unreadable',
                'unresolved',
            ].includes(state)
                ? state
                : null)
        );
    }

    function diagnosticObservation(value) {
        const raw = diagnosticRecord(value);
        const code = diagnosticCode(raw.code ?? raw.option);

        return code === null
            ? null
            : {
                  code,
                  mean: diagnosticNumber(raw.mean ?? raw.bubble_mean, null),
                  classification: diagnosticText(
                      raw.classification ?? raw.threshold_status,
                      null,
                  ),
              };
    }

    function diagnosticObservations(row, proposal, nested, detected) {
        const source = diagnosticFirstArray(
            diagnosticRecord(row.machine).option_observations,
            nested.option_observations,
            detected.option_observations,
            proposal.option_observations,
            row.option_observations,
            row.option_evidence,
        );

        return diagnosticArray(source)
            .map(diagnosticObservation)
            .filter(Boolean);
    }

    function diagnosticOptions(row, proposal, nested, detected, observations) {
        const geometry = diagnosticRecord(row.geometry ?? proposal.geometry);
        const observationByCode = new Map(
            observations.map((observation) => [observation.code, observation]),
        );
        const sources = [
            row.options,
            diagnosticRecord(row.machine).options,
            nested.options,
            detected.options,
            proposal.options,
            geometry.options,
            row.option_codes,
            proposal.option_codes,
            observations,
            diagnosticUniqueCodes(
                diagnosticFirstArray(
                    diagnosticRecord(row.machine).observed_options,
                    nested.observed_options,
                    detected.observed_options,
                    proposal.observed_options,
                    row.observed_options,
                ),
            ),
        ];
        const seen = new Set();
        const options = [];

        for (const source of sources) {
            for (const value of diagnosticArray(source)) {
                const raw = diagnosticRecord(value);
                const code = diagnosticCode(
                    raw.code ?? raw.option ?? raw.value ?? value,
                );
                if (code === null || seen.has(code)) continue;

                const observation = observationByCode.get(code);
                seen.add(code);
                options.push({
                    code,
                    region:
                        raw.region ??
                        raw.rect ??
                        raw.option_rect ??
                        raw.geometry ??
                        null,
                    mean: diagnosticNumber(
                        raw.mean ?? raw.bubble_mean ?? observation?.mean,
                        null,
                    ),
                    threshold_delta: diagnosticNumber(
                        raw.threshold_delta,
                        null,
                    ),
                    threshold_status: diagnosticText(
                        raw.threshold_status ?? raw.classification,
                        observation?.classification ?? null,
                    ),
                    crop_png_base64: null,
                });
            }
        }

        return options;
    }

    function diagnosticAsset(source, fallback, url, kind) {
        const primary = diagnosticRecord(source);
        const secondary = diagnosticRecord(fallback);
        const primaryMetadata = diagnosticRecord(primary.metadata);
        const secondaryMetadata = diagnosticRecord(secondary.metadata);
        const sha256 = diagnosticText(
            primary.sha256 ??
                primary.hash ??
                primary.asset_hash ??
                secondary.sha256 ??
                secondary.hash ??
                secondary.asset_hash,
            null,
        );
        const assetUrl = diagnosticText(
            url ??
                primary.url ??
                primary.href ??
                secondary.url ??
                secondary.href,
            null,
        );
        const asset = {
            id: diagnosticPositiveInteger(primary.id ?? secondary.id, null),
            kind: diagnosticText(
                primary.kind ??
                    primary.type ??
                    secondary.kind ??
                    secondary.type,
                kind,
            ),
            page_no: diagnosticNumber(
                primary.page_no ??
                    primary.page ??
                    secondary.page_no ??
                    secondary.page,
                null,
            ),
            mime: diagnosticText(primary.mime ?? secondary.mime, null),
            width: diagnosticPositiveInteger(
                primary.width ??
                    primaryMetadata.width ??
                    secondary.width ??
                    secondaryMetadata.width,
                null,
            ),
            height: diagnosticPositiveInteger(
                primary.height ??
                    primaryMetadata.height ??
                    secondary.height ??
                    secondaryMetadata.height,
                null,
            ),
            sha256,
            url: assetUrl,
        };

        return Object.values(asset).some(
            (value) => value !== null && value !== undefined,
        )
            ? asset
            : null;
    }

    function diagnosticReview(value, machineHash) {
        const raw = diagnosticRecord(value);
        const decision = diagnosticText(raw.decision, null);
        if (decision === null) return null;

        const reviewHash = diagnosticText(
            raw.evidence_hash ?? raw.reviewed_evidence_hash,
            null,
        );
        const reviewer = diagnosticRecord(raw.reviewer);
        const reviewerId =
            reviewer.id ?? raw.reviewer_id ?? raw.actor_id ?? null;
        const reviewerName = diagnosticText(
            reviewer.display_name ??
                reviewer.name ??
                raw.reviewer_name ??
                raw.actor_name,
            null,
        );

        return {
            id: diagnosticPositiveInteger(raw.id, null),
            diagnostic_item_evidence_id: diagnosticPositiveInteger(
                raw.diagnostic_item_evidence_id ?? raw.item_evidence_id,
                null,
            ),
            revision: diagnosticInteger(raw.revision, null),
            parent_revision: diagnosticInteger(raw.parent_revision, null),
            decision,
            selected_option: diagnosticCode(
                raw.selected_option ?? raw.final_option,
            ),
            reason_code: diagnosticText(raw.reason_code ?? raw.reason, null),
            note: diagnosticText(raw.note, null),
            reviewer:
                reviewerId !== null || reviewerName !== null
                    ? {
                          id: reviewerId,
                          display_name: reviewerName,
                      }
                    : null,
            reviewed_at: diagnosticText(
                raw.reviewed_at ?? raw.created_at,
                null,
            ),
            evidence_hash: reviewHash,
            stale:
                raw.stale === true ||
                raw.review_stale === true ||
                (reviewHash !== null &&
                    machineHash !== null &&
                    reviewHash.toLowerCase() !== machineHash.toLowerCase()),
        };
    }

    function diagnosticReviewRows(row, machineHash) {
        const raw = diagnosticRecord(row);
        const values = [
            raw.latest_review,
            raw.current_review,
            raw.review,
            ...diagnosticArray(raw.reviews).toReversed(),
        ].filter(Boolean);
        const seen = new Set();
        const reviews = [];

        for (const value of values) {
            const review = diagnosticReview(value, machineHash);
            if (!review) continue;
            const key = review.revision ?? review.id ?? reviews.length;
            if (seen.has(key)) continue;
            seen.add(key);
            reviews.push(review);
        }

        return reviews.toSorted(
            (left, right) => (right.revision ?? 0) - (left.revision ?? 0),
        );
    }

    export function mapDiagnosticEvidenceItem(
        item,
        capture = {},
        context = {},
    ) {
        const row = diagnosticRecord(item);
        const captureRecord = diagnosticRecord(capture);
        const evidence = diagnosticRecord(context.evidence);
        const proposal = diagnosticRecord(row.machine_proposal);
        const nested = diagnosticRecord(proposal.detected);
        const detected = diagnosticRecord(row.detected);
        const machineSource = diagnosticRecord(row.machine);
        const selectedCandidate =
            machineSource.selected_option ??
            nested.selected_option ??
            detected.selected_option ??
            proposal.selected_option ??
            row.option_code;
        const selectedCode = diagnosticCode(selectedCandidate);
        const state =
            diagnosticState(
                machineSource.state ??
                    nested.state ??
                    detected.state ??
                    proposal.state ??
                    proposal.status ??
                    row.state,
            ) ?? (selectedCode !== null ? 'marked' : 'unresolved');
        const selectedOption = state === 'marked' ? selectedCode : null;
        const observations = diagnosticObservations(
            row,
            proposal,
            nested,
            detected,
        );
        const observedOptions = diagnosticUniqueCodes(
            diagnosticFirstArray(
                machineSource.observed_options,
                nested.observed_options,
                detected.observed_options,
                proposal.observed_options,
                row.observed_options,
            ),
        );
        if (observedOptions.length === 0 && selectedOption !== null) {
            observedOptions.push(selectedOption);
        }
        const confidence = diagnosticNumber(
            machineSource.confidence ??
                nested.confidence ??
                detected.confidence ??
                row.machine_confidence ??
                proposal.confidence ??
                row.confidence,
            null,
        );
        const evidenceHash = diagnosticText(
            row.evidence_hash ??
                machineSource.evidence_hash ??
                nested.evidence_hash ??
                detected.evidence_hash ??
                proposal.evidence_hash,
            null,
        );
        const issueCodes = diagnosticUniqueCodes(
            diagnosticFirstArray(
                machineSource.issue_codes,
                nested.issue_codes,
                detected.issue_codes,
                proposal.issue_codes,
                row.issue_codes,
            ),
        );
        const options = diagnosticOptions(
            row,
            proposal,
            nested,
            detected,
            observations,
        );
        const evidenceAssets = diagnosticRecord(evidence.assets);
        const alignedEvidence = diagnosticRecord(
            context.alignedEvidenceAsset ?? evidenceAssets.aligned,
        );
        const alignedCapture = diagnosticRecord(
            context.alignedCaptureAsset ??
                diagnosticRecord(captureRecord.assets).aligned,
        );
        const pageAsset = diagnosticAsset(
            row.page_asset ?? alignedEvidence,
            alignedCapture,
            diagnosticRecord(context.assetUrls).aligned,
            'aligned_page',
        );
        const cropAsset = diagnosticAsset(
            row.asset ?? row.crop_asset,
            null,
            diagnosticRecord(context.assetUrls).crop,
            'dedicated_crop',
        );
        const geometrySource = diagnosticRecord(
            row.geometry ?? proposal.geometry,
        );
        const itemRect =
            geometrySource.item_rect ??
            geometrySource.itemRect ??
            row.region ??
            row.item_rect ??
            proposal.region ??
            null;
        const cropRect =
            geometrySource.crop_rect ??
            geometrySource.cropRect ??
            row.crop_rect ??
            itemRect;
        const evidenceVersion =
            diagnosticText(
                row.evidence_version ??
                    proposal.evidence_version ??
                    context.evidenceVersion ??
                    diagnosticRecord(evidence).evidence_version,
                'v1',
            ) ?? 'v1';
        const pageNo =
            diagnosticPositiveInteger(
                row.page_no ??
                    row.page ??
                    proposal.page_no ??
                    context.pageNo ??
                    diagnosticRecord(evidence).page_no,
                1,
            ) ?? 1;
        const geometry = {
            item_rect: itemRect,
            crop_rect: cropRect,
            options,
            aligned_asset: pageAsset,
            crop_asset: cropAsset,
            crop_source: row.crop_source ?? geometrySource.crop_source ?? null,
            geometry_manifest_hash: diagnosticText(
                row.geometry_manifest_hash ??
                    geometrySource.geometry_manifest_hash ??
                    geometrySource.geometry_hash ??
                    context.geometryManifestHash,
                null,
            ),
            evidence_version: evidenceVersion,
            page_no: pageNo,
        };
        const reviews = diagnosticReviewRows(row, evidenceHash);
        const currentReview = reviews[0] ?? null;
        const parentVersion = diagnosticInteger(
            row.parent_version ??
                row.version ??
                captureRecord.version ??
                context.parentVersion,
            0,
        );
        const itemRevision = diagnosticInteger(
            row.item_revision ?? currentReview?.revision,
            0,
        );
        const captureId = diagnosticPositiveInteger(
            row.capture_id ?? captureRecord.id ?? context.captureId,
            null,
        );
        const itemId = diagnosticCode(
            row.item_id ??
                row.item_key ??
                row.native_label ??
                proposal.item_id ??
                proposal.native_label ??
                row.label,
        );
        const machine = {
            state,
            selected_option: selectedOption,
            confidence,
            observed_options: observedOptions,
            option_observations: observations,
            issue_codes: issueCodes,
            evidence_hash: evidenceHash,
        };

        return {
            id: diagnosticPositiveInteger(row.id, null),
            capture_id: captureId,
            item_id: itemId,
            native_label: diagnosticText(
                row.native_label ?? row.item_key ?? itemId,
                itemId,
            ),
            label: diagnosticText(row.label ?? row.item_key ?? itemId, itemId),
            position: diagnosticInteger(row.position, null),
            area_code: diagnosticText(
                row.area_code ?? row.area_key ?? proposal.area_code,
                null,
            ),
            question_no:
                row.question_no === null || row.question_no === undefined
                    ? null
                    : diagnosticInteger(row.question_no, null),
            page_no: pageNo,
            region: itemRect,
            crop_rect: cropRect,
            options,
            machine,
            detected: { ...machine },
            machine_proposal: {
                ...machine,
                options,
            },
            state,
            selected_option: selectedOption,
            confidence,
            issue_codes: issueCodes,
            option_evidence: observations,
            evidence_hash: evidenceHash,
            evidence_version: evidenceVersion,
            parent_version: parentVersion,
            version: parentVersion,
            item_revision: itemRevision,
            review: currentReview,
            current_review: currentReview,
            review_history: reviews,
            reviewable: context.reviewable !== false,
            asset: cropAsset,
            page_asset: pageAsset,
            crop_source: geometry.crop_source,
            geometry,
        };
    }

    export function mapDiagnosticEvidencePage(input = {}) {
        const capture = diagnosticRecord(input.capture);
        const evidence = diagnosticRecord(input.captureEvidence);
        const rawItems = diagnosticArray(input.items);
        const assetUrls = diagnosticRecord(input.assetUrls);
        const captureId = diagnosticPositiveInteger(
            capture.id ?? input.captureId,
            null,
        );
        if (captureId === null) return null;

        const evidenceAssets = diagnosticRecord(evidence.assets);
        const alignedEvidenceAsset = diagnosticRecord(
            evidenceAssets.aligned ?? evidence.aligned_image,
        );
        const alignedCaptureAsset = diagnosticRecord(
            diagnosticRecord(capture.assets).aligned,
        );
        const firstRawItem = diagnosticRecord(rawItems[0]);
        const pageNo =
            diagnosticPositiveInteger(
                evidence.page_no ?? firstRawItem.page_no ?? firstRawItem.page,
                1,
            ) ?? 1;
        const mappedItems = rawItems.map((item) =>
            mapDiagnosticEvidenceItem(item, capture, {
                evidence,
                captureId,
                pageNo,
                parentVersion: capture.version ?? input.parentVersion,
                evidenceVersion: evidence.evidence_version,
                assetUrls,
                alignedEvidenceAsset,
                alignedCaptureAsset,
                geometryManifestHash:
                    evidence.geometry_manifest_hash ??
                    diagnosticRecord(evidence.geometry).geometry_manifest_hash,
                reviewable: input.reviewable !== false,
            }),
        );
        const firstItem = mappedItems[0] ?? null;
        const evidenceVersion =
            diagnosticText(
                evidence.evidence_version ?? firstItem?.evidence_version,
                'v1',
            ) ?? 'v1';
        const captureEvidenceHash = diagnosticText(
            capture.evidence_hash ?? evidence.evidence_hash,
            null,
        );
        const pageAsset = diagnosticAsset(
            alignedEvidenceAsset,
            alignedCaptureAsset,
            assetUrls.aligned,
            'aligned_page',
        );
        const summary = diagnosticRecord(evidence.summary);
        const needsReviewTotal = mappedItems.filter((item) => {
            const machine = diagnosticRecord(item.machine);

            return (
                machine.state !== 'marked' ||
                machine.observed_options.length !== 1 ||
                machine.issue_codes.length > 0
            );
        }).length;
        const reviewedTotal = mappedItems.filter(
            (item) => item.review !== null,
        ).length;
        const rawGroups = diagnosticArray(
            evidence.groups ?? evidence.group_navigation,
        )
            .map((group) => diagnosticRecord(group))
            .map((group) => ({
                key: diagnosticText(
                    group.key ?? group.area_key ?? group.group_type,
                    null,
                ),
                label:
                    diagnosticText(
                        group.label ??
                            group.key ??
                            group.area_key ??
                            group.group_type,
                        'Evidence',
                    ) ?? 'Evidence',
                group_type: diagnosticText(group.group_type, null),
                area_key: diagnosticText(group.area_key, null),
                area_position: diagnosticNumber(group.area_position, null),
                total: diagnosticInteger(group.total, 0) ?? 0,
                mismatch_total: diagnosticInteger(group.mismatch_total, 0) ?? 0,
                page_count: diagnosticPositiveInteger(group.page_count, 1) ?? 1,
            }))
            .filter((group) => group.key !== null);
        const groups =
            rawGroups.length > 0
                ? rawGroups
                : [
                      {
                          key: 'all',
                          label: 'All areas',
                          group_type: 'all',
                          area_key: null,
                          area_position: null,
                          total: mappedItems.length,
                          mismatch_total: needsReviewTotal,
                          page_count: 1,
                      },
                  ];
        const width = diagnosticPositiveInteger(
            pageAsset?.width ??
                alignedEvidenceAsset.width ??
                diagnosticRecord(evidence.aligned_image).width,
            null,
        );
        const height = diagnosticPositiveInteger(
            pageAsset?.height ??
                alignedEvidenceAsset.height ??
                diagnosticRecord(evidence.aligned_image).height,
            null,
        );

        return {
            method:
                diagnosticText(evidence.method, 'pre_shaded') ?? 'pre_shaded',
            capture_id: captureId,
            area: 'all',
            total: mappedItems.length,
            mismatch_total:
                diagnosticInteger(
                    evidence.mismatch_total ??
                        summary.mismatch ??
                        summary.ambiguous,
                    needsReviewTotal,
                ) ?? needsReviewTotal,
            needs_review_total:
                diagnosticInteger(
                    evidence.needs_review_total,
                    needsReviewTotal,
                ) ?? needsReviewTotal,
            reviewed_total:
                diagnosticInteger(evidence.reviewed_total, reviewedTotal) ??
                reviewedTotal,
            page: 1,
            per_page: 50,
            page_count: 1,
            filter: 'all',
            evidence_version: evidenceVersion,
            evidence_hash: captureEvidenceHash,
            capture_page_no: pageNo,
            parent_version: diagnosticInteger(capture.version, 0),
            reviewable: input.reviewable !== false,
            blind_review: true,
            presentation: 'blind',
            lifecycle: diagnosticText(capture.status, null),
            status: diagnosticText(capture.status, null),
            review_note_max:
                diagnosticInteger(evidence.review_note_max, 500) ?? 500,
            review_reasons: diagnosticArray(
                evidence.review_reasons ?? evidence.reasons,
            )
                .map((reason) => {
                    const value = diagnosticRecord(reason);

                    return {
                        code: diagnosticText(value.code, null),
                        label:
                            diagnosticText(
                                value.label ?? value.name ?? value.code,
                                null,
                            ) ?? '',
                    };
                })
                .filter(
                    (reason) => reason.code !== null && reason.label !== '',
                ),
            groups,
            items: mappedItems,
            lazy_asset_urls: {
                input: diagnosticText(assetUrls.input, null),
                aligned: diagnosticText(assetUrls.aligned, null),
                overlay: diagnosticText(assetUrls.overlay, null),
            },
            sheet_map: {
                page_no: pageNo,
                width,
                height,
                sha256: pageAsset?.sha256 ?? null,
                items: mappedItems,
                document_fields: [],
            },
        };
    }
    function diagnosticEvidenceVersion(value, fallback = 1) {
        const parsed = diagnosticInteger(value, null);
        if (parsed !== null && parsed > 0) return parsed;

        const text = diagnosticText(value, null);
        const match = text?.match(/(\d+)/);

        return match ? Number(match[1]) : fallback;
    }

    export function buildDiagnosticReviewPayload(
        payload = {},
        row = {},
        capture = {},
        fallbackIdempotencyKey = null,
    ) {
        const payloadRecord = diagnosticRecord(payload);
        const rowRecord = diagnosticRecord(row);
        const captureRecord = diagnosticRecord(capture);
        const decision = diagnosticText(payloadRecord.decision, null);
        const rowMachine = diagnosticRecord(rowRecord.machine);
        const rowDetected = diagnosticRecord(rowRecord.detected);
        const rowProposal = diagnosticRecord(rowRecord.machine_proposal);
        const selectableOption = diagnosticCode(
            payloadRecord.selected_option ??
                payloadRecord.selectable_option ??
                payloadRecord.final_option ??
                (decision === 'confirm_machine'
                    ? (rowMachine.selected_option ??
                      rowDetected.selected_option ??
                      rowProposal.selected_option ??
                      rowRecord.option_code)
                    : null),
        );
        const finalOption =
            decision === 'confirm_machine' || decision === 'set_option'
                ? selectableOption
                : null;
        const evidenceHash = diagnosticText(
            payloadRecord.reviewed_machine_evidence_hash ??
                rowMachine.evidence_hash ??
                rowDetected.evidence_hash ??
                rowRecord.evidence_hash ??
                rowProposal.evidence_hash,
            null,
        );
        const expectedCaptureVersion = diagnosticInteger(
            captureRecord.version ??
                rowRecord.parent_version ??
                rowRecord.version,
            0,
        );
        const expectedItemRevision = diagnosticInteger(
            payloadRecord.expected_item_revision ??
                rowRecord.item_revision ??
                diagnosticRecord(rowRecord.review).revision,
            0,
        );
        const expectedEvidenceVersion = diagnosticEvidenceVersion(
            payloadRecord.expected_evidence_version ??
                rowRecord.evidence_version,
            1,
        );
        const idempotencyKey = diagnosticText(
            payloadRecord.idempotency_key ?? fallbackIdempotencyKey,
            null,
        );

        return {
            decision,
            final_option: finalOption,
            reason: payloadRecord.reason_code ?? payloadRecord.reason ?? null,
            note: payloadRecord.note ?? null,
            expected_capture_version: expectedCaptureVersion,
            expected_item_revision: expectedItemRevision,
            expected_evidence_version: expectedEvidenceVersion,
            reviewed_evidence_hash: evidenceHash,
            idempotency_key: idempotencyKey,
        };
    }
