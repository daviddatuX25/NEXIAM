@include('emails.partials.header', ['title' => 'Reset Your Password — SecureCAT'])

                            <h2 style="margin: 0 0 16px; font-size: 22px; font-weight: 700; color: #0f172a; line-height: 1.3;">
                                Password Reset Request
                            </h2>

                            <p style="margin: 0 0 16px; font-size: 15px; color: #475569; line-height: 1.65;">
                                We received a request to reset the password for your Applicant Portal account associated with <strong>{{ $applicant->email }}</strong>.
                            </p>

                            <p style="margin: 0 0 24px; font-size: 15px; color: #475569; line-height: 1.65;">
                                Click the button below to set a new password:
                            </p>

                            {{-- CTA Button --}}
                            <table role="presentation" cellspacing="0" cellpadding="0" width="100%">
                                <tr>
                                    <td align="center" style="padding: 8px 0 32px;">
                                        <a href="{{ $resetUrl }}" style="display: inline-block; padding: 14px 36px; background: linear-gradient(135deg, #1e3a5f 0%, #2563eb 100%); color: #ffffff; font-size: 15px; font-weight: 600; text-decoration: none; border-radius: 8px;">
                                            Reset Password
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 0 0 24px;">

                            {{-- Security Notice --}}
                            <table role="presentation" cellspacing="0" cellpadding="0" width="100%" style="margin-bottom: 16px;">
                                <tr>
                                    <td style="background-color: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 12px 20px;">
                                        <p style="margin: 0; font-size: 13px; color: #92400e; line-height: 1.5;">
                                            <strong>Security Notice:</strong> This link expires in 15 minutes. If you did not request a password reset, no action is needed &mdash; your account remains secure.
                                        </p>
                                    </td>
                                </tr>
                            </table>

@include('emails.partials.footer')
