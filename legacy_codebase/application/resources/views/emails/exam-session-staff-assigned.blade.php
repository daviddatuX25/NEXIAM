@include('emails.partials.header', ['title' => 'Exam Session Assignment'])

                            {{-- Info Badge --}}
                            <table role="presentation" cellspacing="0" cellpadding="0" style="margin-bottom: 24px;">
                                <tr>
                                    <td style="background-color: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 12px 20px;">
                                        <span style="font-size: 14px; font-weight: 600; color: #166534;">&#128221; Session Assignment</span>
                                    </td>
                                </tr>
                            </table>

                            <h2 style="margin: 0 0 16px; font-size: 22px; font-weight: 700; color: #0f172a; line-height: 1.3;">
                                You Have Been Assigned to an Exam Session
                            </h2>

                            <p style="margin: 0 0 20px; font-size: 15px; color: #475569; line-height: 1.65;">
                                Hello, <strong>{{ $staffName }}</strong>. You have been assigned to proctor or administer the following exam session:
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
                                                    <span style="font-size: 15px; font-weight: 600; color: #0f172a;">{{ $sessionTime }}@if($endTime) — {{ $endTime }}@endif</span>
                                                </td>
                                            </tr>
                                            @endif
                                            <tr>
                                                <td style="padding: 8px 0 4px;">
                                                    <span style="font-size: 12px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em;">Room</span><br>
                                                    <span style="font-size: 15px; font-weight: 600; color: #0f172a;">{{ $sessionRoom }}@if($roomBuilding) ({{ $roomBuilding }})@endif</span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 8px 0 4px;">
                                                    <span style="font-size: 12px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em;">Applicants Assigned</span><br>
                                                    <span style="font-size: 15px; font-weight: 600; color: #0f172a;">{{ $applicantCount }} {{ Str::plural('applicant', $applicantCount) }}</span>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            {{-- Reminder Notice --}}
                            <table role="presentation" cellspacing="0" cellpadding="0" width="100%" style="margin-bottom: 24px;">
                                <tr>
                                    <td style="background-color: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 12px 20px;">
                                        <p style="margin: 0; font-size: 13px; color: #1e40af; line-height: 1.5;">
                                            <strong>Reminder:</strong> Please ensure the testing room is prepared and all materials are ready before the session begins.
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
