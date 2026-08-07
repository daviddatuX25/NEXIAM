@include('emails.partials.header', ['title' => 'Exam Reminder'])

                            {{-- Reminder Badge --}}
                            <table role="presentation" cellspacing="0" cellpadding="0" style="margin-bottom: 24px;">
                                <tr>
                                    <td style="background-color: #fefce8; border: 1px solid #fde68a; border-radius: 8px; padding: 12px 20px;">
                                        <span style="font-size: 14px; font-weight: 600; color: #854d0e;">&#9200; Exam in {{ $daysUntil }} {{ $daysUntil === 1 ? 'day' : 'days' }}</span>
                                    </td>
                                </tr>
                            </table>

                            <h2 style="margin: 0 0 16px; font-size: 22px; font-weight: 700; color: #0f172a; line-height: 1.3;">
                                Your Exam is Coming Up
                            </h2>

                            <p style="margin: 0 0 20px; font-size: 15px; color: #475569; line-height: 1.65;">
                                Hello, <strong>{{ $applicantName }}</strong>. This is a friendly reminder that your exam session is <strong>{{ $daysUntil }} {{ $daysUntil === 1 ? 'day' : 'days' }}</strong> away.
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
                                            @if($sessionTime)
                                            <tr>
                                                <td style="padding: 8px 0 4px;">
                                                    <span style="font-size: 12px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em;">Time</span><br>
                                                    <span style="font-size: 15px; font-weight: 600; color: #0f172a;">{{ $sessionTime }}</span>
                                                </td>
                                            </tr>
                                            @endif
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 0 0 24px;">

                            <p style="margin: 0 0 8px; font-size: 13px; font-weight: 600; color: #64748b;">Before your exam:</p>
                            <table role="presentation" cellspacing="0" cellpadding="0" style="margin: 0 0 24px;">
                                <tr><td style="padding: 4px 0; font-size: 13px; color: #64748b;">&#8226;&nbsp; Arrive at least 15 minutes early</td></tr>
                                <tr><td style="padding: 4px 0; font-size: 13px; color: #64748b;">&#8226;&nbsp; Bring a valid ID</td></tr>
                                <tr><td style="padding: 4px 0; font-size: 13px; color: #64748b;">&#8226;&nbsp; Check your portal for any updates</td></tr>
                            </table>

                            {{-- CTA Button --}}
                            <table role="presentation" cellspacing="0" cellpadding="0" width="100%">
                                <tr>
                                    <td align="center" style="padding: 8px 0 16px;">
                                        <a href="{{ $portalUrl }}" style="display: inline-block; padding: 14px 36px; background: linear-gradient(135deg, #1e3a5f 0%, #2563eb 100%); color: #ffffff; font-size: 15px; font-weight: 600; text-decoration: none; border-radius: 8px;">
                                            View Details
                                        </a>
                                    </td>
                                </tr>
                            </table>

@include('emails.partials.footer')
