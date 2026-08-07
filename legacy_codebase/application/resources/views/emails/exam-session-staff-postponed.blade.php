@include('emails.partials.header', ['title' => 'Exam Session Postponed'])

                            {{-- Warning Badge --}}
                            <table role="presentation" cellspacing="0" cellpadding="0" style="margin-bottom: 24px;">
                                <tr>
                                    <td style="background-color: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 12px 20px;">
                                        <span style="font-size: 14px; font-weight: 600; color: #92400e;">&#9888;&#65039; Session Postponed</span>
                                    </td>
                                </tr>
                            </table>

                            <h2 style="margin: 0 0 16px; font-size: 22px; font-weight: 700; color: #0f172a; line-height: 1.3;">
                                An Exam Session Has Been Postponed
                            </h2>

                            <p style="margin: 0 0 20px; font-size: 15px; color: #475569; line-height: 1.65;">
                                Hello, <strong>{{ $staffName }}</strong>. A session you were assigned to has been postponed and reverted to draft status.
                            </p>

                            {{-- Original Session Details --}}
                            <table role="presentation" cellspacing="0" cellpadding="0" width="100%" style="margin-bottom: 24px;">
                                <tr>
                                    <td style="background-color: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 16px 20px;">
                                        <p style="margin: 0 0 8px; font-size: 12px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em;">Postponed Session</p>
                                        <table role="presentation" cellspacing="0" cellpadding="0" width="100%">
                                            <tr>
                                                <td style="padding: 4px 0;">
                                                    <span style="font-size: 12px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em;">Date</span><br>
                                                    <span style="font-size: 15px; font-weight: 600; color: #991b1b; text-decoration: line-through;">{{ $sessionDate }}</span>
                                                </td>
                                            </tr>
                                            @if($sessionTime)
                                            <tr>
                                                <td style="padding: 8px 0 4px;">
                                                    <span style="font-size: 12px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em;">Time</span><br>
                                                    <span style="font-size: 15px; font-weight: 600; color: #991b1b; text-decoration: line-through;">{{ $sessionTime }}</span>
                                                </td>
                                            </tr>
                                            @endif
                                            <tr>
                                                <td style="padding: 8px 0 4px;">
                                                    <span style="font-size: 12px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em;">Room</span><br>
                                                    <span style="font-size: 15px; font-weight: 600; color: #991b1b; text-decoration: line-through;">{{ $sessionRoom }}@if($roomBuilding) ({{ $roomBuilding }})@endif</span>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            {{-- Info Notice --}}
                            <table role="presentation" cellspacing="0" cellpadding="0" width="100%" style="margin-bottom: 24px;">
                                <tr>
                                    <td style="background-color: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 12px 20px;">
                                        <p style="margin: 0; font-size: 13px; color: #1e40af; line-height: 1.5;">
                                            <strong>Note:</strong> All assigned applicants have been notified of the postponement. You will receive a new assignment notification when the session is rescheduled.
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            {{-- CTA Button --}}
                            <table role="presentation" cellspacing="0" cellpadding="0" width="100%">
                                <tr>
                                    <td align="center" style="padding: 8px 0 16px;">
                                        <a href="{{ $dashboardUrl }}" style="display: inline-block; padding: 14px 36px; background: linear-gradient(135deg, #1e3a5f 0%, #2563eb 100%); color: #ffffff; font-size: 15px; font-weight: 600; text-decoration: none; border-radius: 8px;">
                                            View Session Details
                                        </a>
                                    </td>
                                </tr>
                            </table>

@include('emails.partials.footer')
