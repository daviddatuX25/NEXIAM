@include('emails.partials.header', ['title' => 'Exam Session Cancelled'])

                            {{-- Alert Badge --}}
                            <table role="presentation" cellspacing="0" cellpadding="0" style="margin-bottom: 24px;">
                                <tr>
                                    <td style="background-color: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 12px 20px;">
                                        <span style="font-size: 14px; font-weight: 600; color: #991b1b;">&#10007; Session Cancelled</span>
                                    </td>
                                </tr>
                            </table>

                            <h2 style="margin: 0 0 16px; font-size: 22px; font-weight: 700; color: #0f172a; line-height: 1.3;">
                                Exam Session Cancelled
                            </h2>

                            <p style="margin: 0 0 16px; font-size: 15px; color: #475569; line-height: 1.65;">
                                Hello, <strong>{{ $applicantName }}</strong>. We would like to inform you that the following exam session has been <strong style="color: #991b1b;">cancelled</strong>:
                            </p>

                            {{-- Session Details --}}
                            <table role="presentation" cellspacing="0" cellpadding="0" width="100%" style="margin-bottom: 24px;">
                                <tr>
                                    <td style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px 20px;">
                                        <table role="presentation" cellspacing="0" cellpadding="0" width="100%">
                                            <tr>
                                                <td style="padding: 4px 0;">
                                                    <span style="font-size: 12px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em;">Date</span><br>
                                                    <span style="font-size: 15px; font-weight: 600; color: #0f172a;">{{ $sessionDate }}</span>
                                                </td>
                                            </tr>
                                            @if($sessionRoom)
                                            <tr>
                                                <td style="padding: 8px 0 4px;">
                                                    <span style="font-size: 12px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em;">Room</span><br>
                                                    <span style="font-size: 15px; font-weight: 600; color: #0f172a;">{{ $sessionRoom }}</span>
                                                </td>
                                            </tr>
                                            @endif
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 0 0 24px; font-size: 15px; color: #475569; line-height: 1.65;">
                                Please check your portal for any rescheduled sessions or further announcements.
                            </p>

                            {{-- CTA Button --}}
                            <table role="presentation" cellspacing="0" cellpadding="0" width="100%">
                                <tr>
                                    <td align="center" style="padding: 8px 0 16px;">
                                        <a href="{{ $portalUrl }}" style="display: inline-block; padding: 14px 36px; background: linear-gradient(135deg, #1e3a5f 0%, #2563eb 100%); color: #ffffff; font-size: 15px; font-weight: 600; text-decoration: none; border-radius: 8px;">
                                            View Portal
                                        </a>
                                    </td>
                                </tr>
                            </table>

@include('emails.partials.footer')
