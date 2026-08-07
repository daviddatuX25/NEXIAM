@include('emails.partials.header', ['title' => 'Your Exam Results Are Available'])

                            {{-- Results Badge --}}
                            <table role="presentation" cellspacing="0" cellpadding="0" style="margin-bottom: 24px;">
                                <tr>
                                    <td style="background-color: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 12px 20px;">
                                        <span style="font-size: 14px; font-weight: 600; color: #166534;">&#128202; Results Available</span>
                                    </td>
                                </tr>
                            </table>

                            <h2 style="margin: 0 0 16px; font-size: 22px; font-weight: 700; color: #0f172a; line-height: 1.3;">
                                Your Exam Results Are Ready
                            </h2>

                            <p style="margin: 0 0 16px; font-size: 15px; color: #475569; line-height: 1.65;">
                                Hello, <strong>{{ $applicantName }}</strong>. Your exam results have been released and are now available in your Applicant Portal.
                            </p>

                            <p style="margin: 0 0 24px; font-size: 15px; color: #475569; line-height: 1.65;">
                                Log in to your portal to view your detailed results.
                            </p>

                            {{-- CTA Button --}}
                            <table role="presentation" cellspacing="0" cellpadding="0" width="100%">
                                <tr>
                                    <td align="center" style="padding: 8px 0 32px;">
                                        <a href="{{ $portalUrl }}" style="display: inline-block; padding: 14px 36px; background: linear-gradient(135deg, #1e3a5f 0%, #2563eb 100%); color: #ffffff; font-size: 15px; font-weight: 600; text-decoration: none; border-radius: 8px;">
                                            View Results
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 0 0 24px;">

                            <p style="margin: 0; font-size: 13px; color: #94a3b8; line-height: 1.5;">
                                If you have questions about your results, please contact the guidance office.
                            </p>

@include('emails.partials.footer')
