<!DOCTYPE html>
<html>
<head>
<style>
    body { font-family: Arial, sans-serif; background: #f9fafb; margin: 0; padding: 20px; }
    .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden; }
    .header { background: #16a34a; padding: 30px; text-align: center; }
    .header h1 { color: white; margin: 0; font-size: 24px; }
    .header p { color: #bbf7d0; margin: 6px 0 0; }
    .body { padding: 30px; }
    .label { font-size: 12px; color: #888; margin-bottom: 2px; }
    .value { font-size: 15px; color: #111; font-weight: bold; margin-bottom: 14px; }
    .credentials { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px; padding: 20px; margin: 20px 0; }
    .credentials h3 { color: #166534; margin: 0 0 12px; font-size: 15px; }
    .cred-row { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px; }
    .cred-label { color: #888; }
    .cred-value { font-weight: bold; color: #111; font-family: monospace; }
    .warning { background: #fef9c3; border: 1px solid #fde047; border-radius: 8px; padding: 12px; font-size: 12px; color: #854d0e; margin-top: 16px; }
    .footer { text-align: center; padding: 20px; color: #aaa; font-size: 11px; border-top: 1px solid #f3f4f6; }
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>Pro_BMS</h1>
        <p>Congratulations! You've been hired 🎉</p>
    </div>
    <div class="body">
        <p>Dear <strong>{{ $employee->first_name }} {{ $employee->last_name }}</strong>,</p>
        <p>We are thrilled to welcome you to our team! Your employment details and system access credentials are below.</p>

        <div class="label">Employee Number</div>
        <div class="value">{{ $employee->employee_number }}</div>

        <div class="label">Department</div>
        <div class="value">{{ $employee->department }}</div>

        <div class="label">Job Title</div>
        <div class="value">{{ $employee->job_title }}</div>

        <div class="label">Start Date</div>
        <div class="value">{{ \Carbon\Carbon::parse($employee->hire_date)->format('M d, Y') }}</div>

        @if($tempPassword)
        <div class="credentials">
            <h3>🔐 Your Login Credentials</h3>
            <div class="cred-row">
                <span class="cred-label">Login URL</span>
                <span class="cred-value">http://localhost:5173/login</span>
            </div>
            <div class="cred-row">
                <span class="cred-label">Email</span>
                <span class="cred-value">{{ $employee->email }}</span>
            </div>
            <div class="cred-row">
                <span class="cred-label">Password</span>
                <span class="cred-value">{{ $tempPassword }}</span>
            </div>
            <div class="warning">
                ⚠️ Please change your password after your first login for security.
            </div>
        </div>
        @endif

        <p style="margin-top:24px; color:#555;">
            Please report to HR on your first day to complete onboarding.
            We look forward to working with you!
        </p>
    </div>
    <div class="footer">Pro_BMS Business Management System</div>
</div>
</body>
</html>