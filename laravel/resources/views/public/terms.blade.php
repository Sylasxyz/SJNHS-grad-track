@extends('layouts.legal', ['heading' => 'Terms and Conditions', 'other' => 'privacy', 'otherLabel' => 'Privacy Policy'])

@section('legal-intro', 'Please read these terms carefully before creating an account or using the Graduate Tracking System.')

@section('legal-content')
  <p>
    Welcome to the San Josef National High School Graduate Tracking System. These Terms and Conditions
    govern your use of the Graduate Tracking System (“the System”), a web-based platform developed to
    support the recording, updating, monitoring, and management of graduate information.
  </p>
  <p>
    By creating an account or accessing the System, you acknowledge that you have read, understood,
    and agreed to these Terms and Conditions.
  </p>

  <h2 class="h6">1. Purpose of the System</h2>
  <p>
    The Graduate Tracking System is designed to provide San Josef National High School with a
    centralized platform for managing graduate and alumni information.
  </p>
  <p>The System may be used to:</p>
  <ul>
    <li>Maintain graduate records;</li>
    <li>Allow graduates to update their information;</li>
    <li>Record employment status and other post-graduation outcomes;</li>
    <li>Monitor graduate information by batch, course, program, or academic year;</li>
    <li>Verify graduate accounts and submitted information;</li>
    <li>Provide school personnel with summary reports and statistical information; and</li>
    <li>Provide system notifications and school announcements.</li>
  </ul>
  <p>
    The System is intended primarily for graduate tracking, monitoring, documentation, and
    institutional evaluation.
  </p>

  <h2 class="h6">2. User Accounts</h2>
  <p>
    Users are responsible for providing accurate information when registering and maintaining their accounts.
  </p>
  <p>You must:</p>
  <ul>
    <li>Provide truthful and accurate information;</li>
    <li>Keep your login credentials confidential;</li>
    <li>Avoid sharing your account with another person;</li>
    <li>Update your graduate information when changes occur; and</li>
    <li>Immediately report suspected unauthorized access to the appropriate school personnel.</li>
  </ul>
  <p>
    Accounts may be subject to verification and approval before access to certain System features is granted.
  </p>

  <h2 class="h6">3. Accuracy of Information</h2>
  <p>
    The System depends on information provided through school records and graduate updates.
  </p>
  <p>
    Users are responsible for ensuring that information submitted through their accounts is accurate and
    up to date. The school may review, verify, correct, or request clarification regarding information when necessary.
  </p>
  <p>
    The System does not automatically collect employment information from external employment databases.
    Employment information may come from available school records, follow-ups, or information voluntarily
    provided by graduates.
  </p>

  <h2 class="h6">4. Account Verification</h2>
  <p>
    Graduate registrations may undergo verification by authorized school personnel.
  </p>
  <p>
    The school may approve, reject, or request clarification regarding an account or submitted information
    when necessary to maintain the accuracy and integrity of graduate records.
  </p>

  <h2 class="h6">5. Acceptable Use</h2>
  <p>
    Users agree to use the System only for legitimate purposes related to graduate tracking and school-related activities.
  </p>
  <p>Users must not:</p>
  <ul>
    <li>Access another person's account without authorization;</li>
    <li>Submit false or misleading information;</li>
    <li>Attempt to gain unauthorized access to System data;</li>
    <li>Modify or interfere with System functionality;</li>
    <li>Use another person's personal information without authorization;</li>
    <li>Attempt to obtain graduate records that they are not authorized to access; or</li>
    <li>Use information obtained from the System for unauthorized purposes.</li>
  </ul>

  <h2 class="h6">6. Access and Authorization</h2>
  <p>
    Access to graduate records is restricted according to user roles and authorization.
  </p>
  <p>
    School administrators, instructors, and designated personnel may access information necessary to perform
    their assigned responsibilities. Graduate users may access and manage information associated with their
    own account, subject to the System's permissions.
  </p>
  <p>
    The System is designed to restrict access to authorized school personnel as part of its privacy and security measures.
  </p>

  <h2 class="h6">7. Reports and Analytics</h2>
  <p>
    The System may generate summary reports and statistical information based on graduate records.
  </p>
  <p>
    Reports and analytics are intended to support school documentation, graduate monitoring, and institutional evaluation.
  </p>
  <p>
    Users must not copy, distribute, publish, or disclose confidential graduate information obtained through
    the System without proper authorization.
  </p>

  <h2 class="h6">8. System Notifications</h2>
  <p>The System may send notifications relating to:</p>
  <ul>
    <li>Registration confirmation;</li>
    <li>Account approval;</li>
    <li>Important school announcements; and</li>
    <li>Other relevant System updates.</li>
  </ul>
  <p>
    The notification feature is intended to keep users informed about important System activities and updates.
  </p>

  <h2 class="h6">9. System Availability</h2>
  <p>
    The Graduate Tracking System is a web-based platform and requires an internet connection.
  </p>
  <p>
    The school and System administrators will make reasonable efforts to maintain the availability and
    reliability of the System. However, temporary interruptions may occur because of maintenance, technical
    problems, network issues, or circumstances beyond the System administrators' control.
  </p>
  <p>
    The System does not currently provide offline functionality.
  </p>

  <h2 class="h6">10. System Limitations</h2>
  <p>
    The Graduate Tracking System is a monitoring and reporting tool.
  </p>
  <p>It does not provide:</p>
  <ul>
    <li>Job placement services;</li>
    <li>Job matching services;</li>
    <li>Automatic employment-data collection;</li>
    <li>Integration with government employment systems;</li>
    <li>Integration with third-party employment databases; or</li>
    <li>Automated messaging beyond basic System notifications.</li>
  </ul>

  <h2 class="h6">11. Privacy and Security</h2>
  <p>
    The school recognizes the importance of protecting graduate information.
  </p>
  <p>
    Personal information and graduate records will be handled with appropriate confidentiality and will be
    accessible only to authorized persons for legitimate System and school-related purposes.
  </p>
  <p>
    The System incorporates secure login authentication and data protection measures intended to prevent
    unauthorized access or misuse.
  </p>
  <p>
    For more information, please see our
    <a href="{{ route('privacy', request()->query('embed') === '1' ? ['embed' => 1] : []) }}">Privacy Policy</a>.
  </p>

  <h2 class="h6">12. Changes to These Terms</h2>
  <p>
    These Terms and Conditions may be updated when necessary to reflect changes to the System, school
    procedures, or applicable requirements.
  </p>
  <p>
    When significant changes are made, users may be informed through the System or other appropriate
    communication channels.
  </p>

  <h2 class="h6">13. Contact</h2>
  <p class="mb-0">
    For questions, concerns, or requests regarding these Terms and Conditions, please contact the appropriate
    administrator or designated personnel of:
  </p>
  <p class="mb-0 mt-2">
    <strong>San Josef National High School</strong><br>
    Graduate Tracking System<br>
    San Josef, Cabanatuan City, Philippines
  </p>
@endsection
