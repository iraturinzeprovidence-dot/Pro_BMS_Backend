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
    .label { font-size: 12px; color: #888; }
    .value { font-size: 15px; color: #111; font-weight: bold; margin-bottom: 12px; }
    .footer { text-align: center; padding: 20px; color: #aaa; font-size: 11px; border-top: 1px solid #f3f4f6; }
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>Pro_BMS</h1>
        <p>Welcome to the Team! 🎉</p>
    </div>
    <div class="body">
        <p>Dear {{ $employee->first_name }} {{ $employee->last_name }},</p>
        <p>We are thrilled to welcome you to our team! Here are your employment details:</p>

        <div class="label">Employee Number</div>
        <div class="value">{{ $employee->employee_number }}</div>

        <div class="label">Department</div>
        <div class="value">{{ $employee->department }}</div>

        <div class="label">Job Title</div>
        <div class="value">{{ $employee->job_title }}</div>

        <div class="label">Start Date</div>
        <div class="value">{{ \Carbon\Carbon::parse($employee->hire_date)->format('M d, Y') }}</div>

        <p style="margin-top:24px; color:#555;">
            Please report to HR on your first day to complete onboarding.
            We look forward to working with you!
        </p>
    </div>
    <div class="footer">Pro_BMS Business Management System</div>
</div>
</body>
</html>