@include('emails.partials.header', ['title' => 'Results Available for Consultation'])

                            {{-- Info Badge --}}
                            <table role="presentation" cellspacing="0" cellpadding="0" style="margin-bottom: 24px;">
                                <tr>
                                    <td style="background-color: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 12px 20px;">
                                        <span style="font-size: 14px; font-weight: 600; color: #1e40af;">&#128202; Results Available for Consultation</span>
                                    </td>
                                </tr>
                            </table>

                            <h2 style="margin: 0 0 16px; font-size: 22px; font-weight: 700; color: #0f172a; line-height: 1.3;">
                                Your Results Are Ready for Consultation
                            </h2>

                            <p style="margin: 0 0 16px; font-size: 15px; color: #475569; line-height: 1.65;">
                                Hello, <strong>{{ $applicantName }}</strong>. Your exam results are now available for face-to-face consultation with the guidance office.
                            </p>

                            {{-- Important Notice --}}
                            <table role="presentation" cellspacing="0" cellpadding="0" width="100%" style="margin-bottom: 24px;">
                                <tr>
                                    <td style="background-color: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 12px 20px;">
                                        <p style="margin: 0; font-size: 13px; color: #92400e; line-height: 1.5;">
                                            <strong>Next Step:</strong> Please wait for further announcement regarding the venue and schedule for your consultation.
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 0 0 24px;">

                            <p style="margin: 0; font-size: 13px; color: #94a3b8; line-height: 1.5;">
                                If you have questions, please contact the guidance office.
                            </p>

@include('emails.partials.footer')
