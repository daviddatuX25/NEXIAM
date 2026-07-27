@include('emails.partials.header', ['title' => 'Application Update — SecureCAT'])

                            <h2 style="margin: 0 0 16px; font-size: 22px; font-weight: 700; color: #0f172a; line-height: 1.3;">
                                Application Status Update
                            </h2>

                            <p style="margin: 0 0 16px; font-size: 15px; color: #475569; line-height: 1.65;">
                                Hello, <strong>{{ $applicantName }}</strong>.
                            </p>

                            @if($referenceNumber)
                            <table role="presentation" cellspacing="0" cellpadding="0" width="100%" style="margin-bottom: 24px;">
                                <tr>
                                    <td style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px 20px;">
                                        <p style="margin: 0 0 4px; font-size: 12px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em;">Reference Number</p>
                                        <p style="margin: 0; font-size: 18px; font-weight: 700; color: #1e40af; letter-spacing: 0.025em;">{{ $referenceNumber }}</p>
                                    </td>
                                </tr>
                            </table>
                            @endif

                            @if($newStatus === 'dismissed')
                            <p style="margin: 0 0 16px; font-size: 15px; color: #475569; line-height: 1.65;">
                                After careful review, we regret to inform you that your application was <strong>not accepted</strong> at this time.
                            </p>

                                @if($rejectionReason)
                                <table role="presentation" cellspacing="0" cellpadding="0" width="100%" style="margin-bottom: 24px;">
                                    <tr>
                                        <td style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px 20px;">
                                            <p style="margin: 0 0 4px; font-size: 12px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em;">Reason</p>
                                            <p style="margin: 0; font-size: 14px; color: #475569; line-height: 1.5;">{{ $rejectionReason }}</p>
                                        </td>
                                    </tr>
                                </table>
                                @endif

                            <p style="margin: 0 0 16px; font-size: 15px; color: #475569; line-height: 1.65;">
                                If you have any questions about this decision, please contact the admissions office.
                            </p>
                            @else
                            <p style="margin: 0 0 16px; font-size: 15px; color: #475569; line-height: 1.65;">
                                Your application status has been updated to <strong>{{ $statusLabel }}</strong>.
                            </p>
                            @endif

                            <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 24px 0;">

                            <p style="margin: 0; font-size: 13px; color: #94a3b8; line-height: 1.5;">
                                This is an update regarding your application to SecureCAT.
                            </p>

@include('emails.partials.footer')
