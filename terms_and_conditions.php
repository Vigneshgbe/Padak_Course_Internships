<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms & Conditions - Padak Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6;
            color: #334155;
            background: linear-gradient(135deg, #fff5f0 0%, #ffffff 50%, #fff8e1 100%);
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        /* Header Section */
        .header-section {
            padding: 2.5rem 0;
            position: relative;
            overflow: hidden;
        }

        .header-content {
            text-align: center;
            margin-bottom: 2rem;
            position: relative;
            z-index: 10;
        }

        .header-icon-wrapper {
            display: inline-flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .header-icon {
            width: 3.5rem;
            height: 3.5rem;
            background: linear-gradient(135deg, #f97316 0%, #fb923c 100%);
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 30px rgba(249, 115, 22, 0.3);
        }

        .header-icon svg {
            width: 1.75rem;
            height: 1.75rem;
            color: white;
        }

        h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: #0f172a;
        }

        .gradient-text {
            background: linear-gradient(135deg, #f97316 0%, #fb923c 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .header-description {
            font-size: 1.125rem;
            color: #64748b;
            max-width: 48rem;
            margin: 0 auto 1.5rem;
        }

        .header-badges {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            font-size: 0.875rem;
            color: #64748b;
        }

        .badge {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .badge svg {
            width: 1rem;
            height: 1rem;
        }

        .badge-green svg { color: #22c55e; }
        .badge-blue svg { color: #3b82f6; }
        .badge-purple svg { color: #a855f7; }

        /* Floating Background Elements */
        .bg-float {
            position: absolute;
            border-radius: 50%;
            filter: blur(60px);
            opacity: 0.1;
            animation: pulse 4s ease-in-out infinite;
        }

        .bg-float-1 {
            top: 2.5rem;
            left: 2.5rem;
            width: 8rem;
            height: 8rem;
            background: #fb923c;
        }

        .bg-float-2 {
            bottom: 2.5rem;
            right: 2.5rem;
            width: 6rem;
            height: 6rem;
            background: #f97316;
            animation-delay: 1s;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.1; }
            50% { transform: scale(1.1); opacity: 0.15; }
        }

        /* Terms Sections */
        .terms-section {
            padding: 2.5rem 0;
        }

        .terms-container {
            max-width: 64rem;
            margin: 0 auto;
        }

        .term-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-radius: 1rem;
            margin-bottom: 1.25rem;
            transition: all 0.3s ease;
            border: 1px solid transparent;
            position: relative;
            overflow: hidden;
        }

        .term-card:hover {
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            background: white;
            border-color: rgba(249, 115, 22, 0.1);
        }

        .term-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 0.25rem;
            background: linear-gradient(90deg, #f97316 0%, #fb923c 100%);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .term-card:hover::before {
            transform: scaleX(1);
        }

        .card-header {
            padding: 1.25rem 1.5rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .card-icon {
            width: 2.5rem;
            height: 2.5rem;
            background: linear-gradient(135deg, #f97316 0%, #fb923c 100%);
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 8px 20px rgba(249, 115, 22, 0.3);
            transition: all 0.3s ease;
        }

        .term-card:hover .card-icon {
            transform: scale(1.1) rotate(3deg);
        }

        .card-icon svg {
            width: 1.25rem;
            height: 1.25rem;
            color: white;
        }

        .card-text {
            flex: 1;
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 0.25rem;
            transition: color 0.3s ease;
        }

        .term-card:hover .card-title {
            color: #f97316;
        }

        .card-description {
            font-size: 0.9375rem;
            color: #64748b;
            line-height: 1.5;
        }

        .toggle-button {
            background: none;
            border: none;
            color: #f97316;
            cursor: pointer;
            padding: 0.5rem;
            transition: all 0.3s ease;
            border-radius: 0.5rem;
        }

        .toggle-button:hover {
            background: #fff7ed;
            color: #ea580c;
        }

        .toggle-button svg {
            width: 1.25rem;
            height: 1.25rem;
            transition: transform 0.3s ease;
        }

        .toggle-button.active svg {
            transform: rotate(180deg);
        }

        .card-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.5s ease;
            border-top: 1px solid #fed7aa;
        }

        .card-content.expanded {
            max-height: 5000px;
        }

        .content-inner {
            padding: 1.5rem;
        }

        .content-item {
            margin-bottom: 1.5rem;
        }

        .content-item:last-child {
            margin-bottom: 0;
        }

        .content-subtitle {
            font-size: 1rem;
            font-weight: 600;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .bullet-dot {
            width: 0.5rem;
            height: 0.5rem;
            background: linear-gradient(135deg, #f97316 0%, #fb923c 100%);
            border-radius: 50%;
        }

        .content-details {
            color: #64748b;
            padding-left: 1rem;
            font-size: 0.9375rem;
            line-height: 1.7;
            white-space: pre-line;
        }

        /* Notice Section */
        .notice-section {
            padding: 1.5rem 0;
            background: linear-gradient(90deg, rgba(249, 115, 22, 0.05) 0%, rgba(251, 146, 60, 0.05) 100%);
            border-top: 1px solid rgba(249, 115, 22, 0.2);
        }

        .notice-card {
            background: rgba(255, 247, 237, 0.8);
            backdrop-filter: blur(10px);
            border: 1px solid #fed7aa;
            border-radius: 1rem;
            padding: 1.5rem;
            max-width: 64rem;
            margin: 0 auto;
        }

        .notice-content {
            display: flex;
            align-items: start;
            gap: 1rem;
        }

        .notice-icon {
            width: 2.5rem;
            height: 2.5rem;
            background: linear-gradient(135deg, #f97316 0%, #fb923c 100%);
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .notice-icon svg {
            width: 1.25rem;
            height: 1.25rem;
            color: white;
        }

        .notice-text h3 {
            font-size: 1.125rem;
            font-weight: 600;
            color: #9a3412;
            margin-bottom: 0.25rem;
        }

        .notice-text p {
            font-size: 0.875rem;
            color: #c2410c;
            line-height: 1.6;
        }

        /* Contact Section */
        .contact-section {
            padding: 3rem 0;
            background: linear-gradient(90deg, rgba(249, 115, 22, 0.1) 0%, rgba(251, 146, 60, 0.1) 100%);
            position: relative;
        }

        .contact-content {
            max-width: 48rem;
            margin: 0 auto;
            text-align: center;
        }

        .contact-header {
            display: inline-flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .contact-icon {
            width: 3.5rem;
            height: 3.5rem;
            background: linear-gradient(135deg, #f97316 0%, #fb923c 100%);
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 30px rgba(249, 115, 22, 0.3);
        }

        .contact-icon svg {
            width: 1.5rem;
            height: 1.5rem;
            color: white;
        }

        .contact-content h2 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: #0f172a;
        }

        .contact-description {
            font-size: 1.125rem;
            color: #64748b;
            margin-bottom: 1.5rem;
            max-width: 40rem;
            margin-left: auto;
            margin-right: auto;
        }

        .button-group {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            align-items: center;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.875rem 1.75rem;
            font-size: 1rem;
            font-weight: 600;
            border-radius: 0.75rem;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background: linear-gradient(135deg, #f97316 0%, #fb923c 100%);
            color: white;
            box-shadow: 0 8px 20px rgba(249, 115, 22, 0.3);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #ea580c 0%, #f97316 100%);
            box-shadow: 0 12px 30px rgba(249, 115, 22, 0.4);
            transform: translateY(-2px);
        }

        .btn-outline {
            background: transparent;
            border: 2px solid #f97316;
            color: #f97316;
        }

        .btn-outline:hover {
            background: #f97316;
            color: white;
        }

        .btn svg {
            width: 1.25rem;
            height: 1.25rem;
        }

        /* Responsive Design */
        @media (min-width: 640px) {
            .button-group {
                flex-direction: row;
                justify-content: center;
            }
        }

        @media (min-width: 768px) {
            h1 {
                font-size: 2.5rem;
            }

            .header-section {
                padding: 3rem 0;
            }

            .terms-section {
                padding: 3rem 0;
            }

            .contact-section {
                padding: 3.5rem 0;
            }

            .header-icon {
                width: 3.5rem;
                height: 3.5rem;
            }

            .header-icon svg {
                width: 1.75rem;
                height: 1.75rem;
            }

            .header-description {
                font-size: 1.25rem;
            }

            .header-badges {
                font-size: 0.875rem;
            }

            .badge svg {
                width: 1rem;
                height: 1rem;
            }

            .card-header {
                padding-bottom: 0.75rem;
            }

            .notice-card {
                padding: 1.5rem;
            }

            .notice-icon {
                width: 2.5rem;
                height: 2.5rem;
            }

            .notice-icon svg {
                width: 1.25rem;
                height: 1.25rem;
            }

            .notice-text h3 {
                font-size: 1.125rem;
            }

            .notice-text p {
                font-size: 0.875rem;
            }

            .contact-icon {
                width: 3.5rem;
                height: 3.5rem;
            }

            .contact-icon svg {
                width: 1.5rem;
                height: 1.5rem;
            }

            .contact-content h2 {
                font-size: 2rem;
            }

            .contact-description {
                font-size: 1.25rem;
            }

            .btn svg {
                width: 1.25rem;
                height: 1.25rem;
            }
        }

        @media (max-width: 639px) {
            .container {
                padding: 0 1rem;
            }

            h1 {
                font-size: 1.75rem;
            }

            .header-description {
                font-size: 1rem;
            }

            .header-badges {
                font-size: 0.75rem;
            }

            .badge svg {
                width: 0.875rem;
                height: 0.875rem;
            }

            .card-header {
                padding: 1rem;
            }

            .card-icon {
                width: 2rem;
                height: 2rem;
            }

            .card-icon svg {
                width: 1rem;
                height: 1rem;
            }

            .card-title {
                font-size: 1.125rem;
            }

            .card-description {
                font-size: 0.875rem;
            }

            .content-inner {
                padding: 1rem;
            }

            .content-subtitle {
                font-size: 0.9375rem;
            }

            .content-details {
                font-size: 0.875rem;
            }

            .notice-card {
                padding: 1rem;
            }

            .notice-icon {
                width: 2rem;
                height: 2rem;
            }

            .notice-icon svg {
                width: 1rem;
                height: 1rem;
            }

            .notice-text h3 {
                font-size: 1rem;
            }

            .notice-text p {
                font-size: 0.8125rem;
            }

            .contact-icon {
                width: 3rem;
                height: 3rem;
            }

            .contact-icon svg {
                width: 1.25rem;
                height: 1.25rem;
            }

            .contact-content h2 {
                font-size: 1.5rem;
            }

            .contact-description {
                font-size: 1rem;
            }

            .btn {
                padding: 0.75rem 1.5rem;
                font-size: 0.9375rem;
            }

            .btn svg {
                width: 1rem;
                height: 1rem;
            }
        }
    </style>
</head>
<body>
    <?php
    $termsData = [
        [
            'icon' => 'handshake',
            'title' => 'Acceptance of Terms',
            'description' => 'By accessing and using our services, you agree to be bound by these terms and conditions.',
            'content' => [
                [
                    'subtitle' => 'Agreement Formation',
                    'details' => 'These Terms and Conditions constitute a legally binding agreement between you and Padak, your branding partner. By accessing our website, enrolling in our courses, applying for internships, or using any of our services, you acknowledge that you have read, understood, and agree to be bound by these terms.

Your use of our website or services indicates your acceptance of these terms in their entirety. If you disagree with any part of these terms, you must not use our services.

This agreement supersedes any prior agreements or understandings regarding the subject matter herein.'
                ],
                [
                    'subtitle' => 'Capacity to Contract',
                    'details' => 'You represent that you are at least 18 years old and have the legal capacity to enter into this agreement. If you are under 18 but at least 16 years old, you may use our services only with involvement and consent from a parent or guardian.

If you are accepting these terms on behalf of a company or organization, you warrant that you have the authority to bind that entity to these terms. You accept responsibility for all activities that occur under your account.

For educational services and internships, additional eligibility requirements may apply as specified in program-specific documentation.'
                ],
                [
                    'subtitle' => 'Updates and Changes',
                    'details' => 'We reserve the right to modify these terms at any time at our sole discretion. Significant changes will be notified to you through email or prominent notice on our website at least 14 days before they take effect.

Continued use of our services after such modifications constitutes acceptance of the updated terms. It is your responsibility to review these terms periodically to stay informed of any updates.

If you do not agree with the revised terms, you may discontinue using our services, subject to any existing contractual obligations or commitments.'
                ]
            ]
        ],
        [
            'icon' => 'users',
            'title' => 'Service Description',
            'description' => 'Overview of the comprehensive services we provide and their scope.',
            'content' => [
                [
                    'subtitle' => 'Digital Marketing and Branding Services',
                    'details' => 'Padak provides comprehensive digital marketing services including but not limited to SEO optimization, social media marketing, PPC advertising, content marketing, analytics, email marketing, and conversion rate optimization. Our goal is to enhance your brand visibility and drive measurable results for your business.

Each marketing service is tailored to your specific business needs, industry, target audience, and objectives. We employ industry best practices, ethical marketing techniques, and data-driven strategies to maximize your ROI.

While we strive for optimal results, specific outcomes such as rankings, engagement rates, or conversion metrics cannot be guaranteed due to the dynamic nature of digital platforms, algorithm changes, and market conditions.'
                ],
                [
                    'subtitle' => 'Educational Courses and Internships',
                    'details' => 'We offer structured educational courses and internship programs in digital marketing, graphic design, web development, video editing, and related fields. These programs are designed to provide both theoretical knowledge and practical skills through live online sessions, assignments, projects, and assessments.

Course details including duration, curriculum, assessment methods, certification requirements, and fees are specified in course-specific documentation provided prior to enrollment. Completion of all requirements is necessary for certification.

Internship programs may include placement opportunities with partner organizations. While we facilitate these placements, acceptance is subject to the partner organization\'s requirements and selection process. Internship terms are outlined in separate internship agreements.'
                ],
                [
                    'subtitle' => 'Development and Creative Services',
                    'details' => 'Our development services include web development, Android application development, hosting services, graphic design, and video editing. These services are performed according to project specifications agreed upon with clients.

Web and application development projects follow defined development processes including requirement gathering, design, development, testing, and deployment phases. Deliverables, timelines, and acceptance criteria are specified in project-specific agreements.

Graphic design and video editing services are provided with specified revision limits as outlined in service agreements. Additional revisions beyond the agreed limit may incur additional charges.

Hosting services include server configuration, maintenance, security monitoring, and technical support as detailed in hosting service agreements.'
                ]
            ]
        ],
        [
            'icon' => 'book',
            'title' => 'Educational Programs',
            'description' => 'Terms specific to our courses, training, and internship programs.',
            'content' => [
                [
                    'subtitle' => 'Enrollment and Registration',
                    'details' => 'Course enrollment requires completion of registration forms and payment of applicable fees. Registration is confirmed only upon receipt of payment and all required documentation. We reserve the right to refuse enrollment at our discretion.

Course materials, access credentials, and schedules will be provided after successful registration. These materials are for your personal use only and subject to intellectual property protections as outlined in these terms.

You are responsible for ensuring your technical setup meets our requirements for participating in online sessions. This includes appropriate hardware, software, and internet connectivity as specified in course documentation.'
                ],
                [
                    'subtitle' => 'Attendance and Participation',
                    'details' => 'Regular attendance and active participation in scheduled sessions are expected and may impact your assessment. Our attendance policy requires at least 80% attendance for course completion and certification eligibility.

Online sessions may be recorded for educational purposes and made available to enrolled students. By participating in these sessions, you consent to such recording unless you explicitly notify the instructor of your objection before the session begins.

Assignments and projects must be submitted by the specified deadlines. Late submissions may result in grade penalties or rejection at the instructor\'s discretion. Extension requests must be made in advance with valid justification.'
                ],
                [
                    'subtitle' => 'Certification and Assessment',
                    'details' => 'Certification requires successful completion of all course requirements including assignments, projects, assessments, and minimum attendance. Certification criteria are specified in course documentation provided upon enrollment.

Assessments are conducted fairly and transparently according to predefined criteria. Grades and feedback are provided within reasonable timeframes after submission deadlines.

We maintain comprehensive academic integrity standards. Plagiarism, cheating, or any form of academic dishonesty may result in failure of the assignment/course or dismissal from the program without refund. We use plagiarism detection tools to verify the originality of submissions.

Certificates issued upon successful completion are verifiable through our verification system. We maintain records of certified students to support verification requests from employers or other institutions with your consent.'
                ]
            ]
        ],
        [
            'icon' => 'credit-card',
            'title' => 'Payment Terms',
            'description' => 'Billing, payment schedules, and financial obligations for our services.',
            'content' => [
                [
                    'subtitle' => 'Fee Structure and Payment Schedule',
                    'details' => 'Service fees are outlined in service agreements, course enrollment documentation, or published price lists. All fees are stated in the specified currency and subject to applicable taxes which will be clearly indicated.

For one-time services, full payment is typically required upon project completion or as specified in the service agreement. For ongoing services, payments are structured monthly, quarterly, or annually based on the service agreement.

Educational program fees must be paid according to the payment schedule provided during enrollment. This may include one-time payment, installment plans, or other arrangements as specified.'
                ],
                [
                    'subtitle' => 'Accepted Payment Methods',
                    'details' => 'We accept various payment methods including bank transfers, credit/debit cards, and digital payment platforms. Payment details and instructions are provided with invoices or during the checkout process.

All payments must be made in the currency specified in your invoice or service agreement. Currency conversion charges, if any, are your responsibility.

For recurring services, we may offer automatic payment options. By selecting such options, you authorize us to charge the specified payment method according to the agreed schedule until the service is cancelled or the payment method is changed.'
                ],
                [
                    'subtitle' => 'Late Payments and Financial Policies',
                    'details' => 'Payments are due on the dates specified in invoices or service agreements. Late payments may incur additional fees at a rate of 1.5% per month on outstanding balances or as specified in your agreement.

We reserve the right to suspend services for accounts with payments overdue by 30 days or more. Service resumption after suspension requires payment of all outstanding balances and may include a reactivation fee.

For educational programs, continued access to course materials, sessions, and assessments is contingent upon maintaining payments according to the agreed schedule. Certification may be withheld until all financial obligations are fulfilled.

Dishonored payments due to insufficient funds or other reasons may incur an administrative fee in addition to any fees charged by payment providers or banks.'
                ]
            ]
        ],
        [
            'icon' => 'shield',
            'title' => 'Client Responsibilities',
            'description' => 'Your obligations and responsibilities when working with our team.',
            'content' => [
                [
                    'subtitle' => 'Information and Access Provision',
                    'details' => 'You agree to provide accurate, complete, and timely information necessary for us to perform our services. This includes business details, marketing objectives, design preferences, technical specifications, and any other information reasonably required.

For services requiring access to your accounts (such as website hosting, social media, or analytics platforms), you are responsible for providing appropriate access credentials and maintaining their security. We recommend creating role-based access where possible rather than sharing primary account credentials.

You must ensure all information provided is factually correct, does not infringe on third-party rights, and complies with all applicable laws and regulations. We are not responsible for verifying the accuracy or legality of information you provide.'
                ],
                [
                    'subtitle' => 'Review and Feedback',
                    'details' => 'You are responsible for reviewing deliverables and providing clear, timely feedback within the timeframes specified in project plans or service agreements. Delays in review or feedback may impact project timelines and deliverables.

Approval of designs, content, campaigns, or other deliverables constitutes acceptance of those elements. Subsequent revision requests beyond agreed revision limits may incur additional charges.

For time-sensitive campaigns or content, we establish approval deadlines. In the absence of timely approval or feedback, we may proceed with publication based on previously approved materials or delay publication until approval is received, depending on the project requirements.'
                ],
                [
                    'subtitle' => 'Compliance and Ethical Standards',
                    'details' => 'You warrant that your business, products, services, and content comply with all applicable laws, regulations, and industry standards. This includes but is not limited to advertising regulations, data protection laws, intellectual property rights, and consumer protection laws.

You are responsible for ensuring that your marketing claims are truthful, substantiated, and compliant with relevant advertising standards. We reserve the right to refuse implementation of content or campaigns that we reasonably believe violate laws or ethical standards.

For regulated industries or content categories (such as financial services, healthcare, or age-restricted products), you must inform us of specific compliance requirements and provide necessary disclaimers or disclosures that must be included in marketing materials.

You agree not to use our services for any illegal, fraudulent, or unethical purposes. We reserve the right to terminate services immediately if we have reasonable belief that our services are being used for such purposes.'
                ]
            ]
        ],
        [
            'icon' => 'code',
            'title' => 'Development Services',
            'description' => 'Terms specific to web development, app development, and technical services.',
            'content' => [
                [
                    'subtitle' => 'Project Specifications and Scope',
                    'details' => 'Web and app development projects are governed by detailed specifications agreed upon before project commencement. These specifications define the scope, functionality, design requirements, and deliverables for the project.

Changes to project specifications after project initiation must be documented and may affect timeline and costs. A formal change request process will be used to evaluate, approve, and implement changes to the original scope.

Unless explicitly included in the project specifications, certain items are considered outside the scope, including content creation, ongoing maintenance, third-party integration fees, and extended support beyond the specified warranty period.'
                ],
                [
                    'subtitle' => 'Development Process and Milestones',
                    'details' => 'Our development process follows industry standard phases including discovery, design, development, testing, and deployment. Each phase includes defined milestones and deliverables as outlined in the project plan.

Client review and approval is required at key milestones. Development proceeds to the next phase only after written approval of the current phase deliverables. Delays in providing feedback or approval may impact the overall project timeline.

For complex projects, we use staging environments for client review before final deployment. The staging environment is provided for a limited time as specified in the project agreement, after which additional staging time may incur maintenance fees.'
                ],
                [
                    'subtitle' => 'Hosting, Maintenance, and Technical Support',
                    'details' => 'Hosting services include server space, bandwidth, and basic configuration as specified in hosting agreements. Server specifications, uptime guarantees, backup frequency, and security measures are detailed in service-specific documentation.

Maintenance services, when included, cover software updates, security patches, and technical troubleshooting. They do not include new feature development, content updates, or issues caused by third-party modifications unless explicitly specified.

Technical support is provided during specified business hours through designated communication channels. Emergency support options, response time commitments, and escalation procedures are outlined in service level agreements.

For app publishing services, we assist with submission to app stores but cannot guarantee approval by third-party platforms. App store fees, developer account requirements, and compliance with platform policies remain your responsibility.'
                ]
            ]
        ],
        [
            'icon' => 'palette',
            'title' => 'Creative Services',
            'description' => 'Terms for graphic design, content creation, and creative deliverables.',
            'content' => [
                [
                    'subtitle' => 'Design Process and Revisions',
                    'details' => 'Our graphic design services follow a structured process including requirement gathering, concept development, initial designs, and refinement. Each project includes a specified number of revision rounds as detailed in your service agreement.

Initial concepts are presented based on your requirements and brand guidelines. You are expected to provide clear feedback on these concepts to guide the refinement process. Vague feedback such as "I don\'t like it" without specific direction may count as a revision round.

Additional revision rounds beyond those included in the service agreement will incur additional charges at our standard hourly rates. Major conceptual changes after initial approval may be treated as new projects with associated costs.'
                ],
                [
                    'subtitle' => 'Content Ownership and Usage Rights',
                    'details' => 'Upon full payment for design services, you receive ownership rights to the final deliverables for the specific uses outlined in the service agreement. We retain the intellectual property rights to preliminary concepts, drafts, and unused designs.

Unless explicitly specified otherwise, we retain the right to display your final designs in our portfolio, case studies, and promotional materials as examples of our work. If you require confidentiality or restricted portfolio usage, this must be negotiated before project commencement.

For designs incorporating licensed elements (such as stock photos, fonts, or illustrations), you receive rights to use these elements within the final deliverable, but separate licenses may be required for other uses. We provide information about any licensed elements used in your projects.'
                ],
                [
                    'subtitle' => 'Video Production and Editing',
                    'details' => 'Video services include pre-production planning, filming (if applicable), editing, and post-production as specified in project agreements. The number of included revision rounds is specified in your service agreement.

You are responsible for providing necessary content, approvals, and feedback according to the production schedule. Delays in providing these may impact project timelines.

For projects requiring filming, you are responsible for securing necessary permissions for filming locations, securing releases from individuals appearing in videos, and ensuring compliance with relevant regulations.

Final video files are provided in agreed formats and resolutions. Additional format conversions or resolutions beyond those specified may incur additional charges. We maintain backup copies of project files for a limited period as specified in service agreements.'
                ]
            ]
        ],
        [
            'icon' => 'share',
            'title' => 'Social Media Management',
            'description' => 'Terms regarding social media account management and content creation.',
            'content' => [
                [
                    'subtitle' => 'Account Access and Management',
                    'details' => 'For social media management services, you grant us the necessary access to your social media accounts through platform-approved methods such as role-based access or authorized third-party tools. We implement security protocols to protect your account credentials.

We manage your accounts according to agreed content strategies, posting schedules, and engagement guidelines. Regular performance reports are provided as specified in your service agreement.

You retain ownership and ultimate responsibility for your social media accounts. We act as authorized agents managing these accounts on your behalf and according to your instructions.'
                ],
                [
                    'subtitle' => 'Content Creation and Approval Process',
                    'details' => 'Social media content is developed according to content calendars typically prepared on a monthly basis. Content calendars are submitted for your review and approval before publication, with specified deadlines for feedback.

Emergency or time-sensitive content may follow expedited approval processes as outlined in your service agreement. In the absence of timely feedback, we may delay publication or proceed with publishing scheduled content based on previously approved guidelines.

While we create custom content for your channels, some elements may incorporate licensed stock images, templates, or music. Usage rights for these elements are limited to social media platforms and may have restrictions on commercial uses outside social media.'
                ],
                [
                    'subtitle' => 'Platform Compliance and Changes',
                    'details' => 'We manage your social media presence in compliance with each platform\'s terms of service, community guidelines, and advertising policies. However, social media platforms frequently change their algorithms, features, and policies beyond our control.

We adapt our strategies to platform changes as they occur, but cannot guarantee specific results or features that may be affected by platform updates. Significant platform changes that materially affect our services will be communicated to you promptly.

If platform changes require additional services or fundamentally alter the nature of our services, we will discuss necessary adjustments to your service agreement. We are not liable for performance impacts resulting from platform-initiated changes.

We are not responsible for platform-imposed restrictions or penalties resulting from historical account activities prior to our management or from client-directed actions that contradict our recommendations regarding platform compliance.'
                ]
            ]
        ],
        [
            'icon' => 'smartphone',
            'title' => 'Mobile App Services',
            'description' => 'Terms related to Android app development and publication.',
            'content' => [
                [
                    'subtitle' => 'App Development Process',
                    'details' => 'Android application development follows a structured process including requirements analysis, UI/UX design, development, testing, and deployment. Each phase requires your review and approval before proceeding to the next stage.

App development is based on approved specifications and designs. Changes to requirements after approval may impact timeline and costs. A formal change request process is used to document and implement changes to the original specifications.

We develop applications according to Android platform guidelines and best practices. However, we cannot guarantee compatibility with all Android devices and versions beyond those specified in the project agreement.'
                ],
                [
                    'subtitle' => 'App Store Publication',
                    'details' => 'We assist with preparing and submitting your application to the Google Play Store, but you must maintain your own developer account. App store fees, taxes, and compliance with platform policies remain your responsibility.

App approval is at the discretion of Google and other app stores. We develop apps to comply with published guidelines, but cannot guarantee approval. If an app is rejected, we will make reasonable efforts to address the issues cited by the platform.

For app publication, you must provide necessary business information, privacy policies, content ratings information, and marketing materials according to platform requirements. Delays in providing these materials may impact publication timelines.'
                ],
                [
                    'subtitle' => 'App Maintenance and Updates',
                    'details' => 'Once published, applications require ongoing maintenance to address platform updates, security issues, and bug fixes. Maintenance services, when included, are detailed in separate maintenance agreements.

Standard maintenance includes compatibility updates for new Android versions, security patches, and bug fixes. It does not include new features, design changes, or content updates unless explicitly specified.

We recommend regular updates to maintain compatibility with the latest Android versions and security standards. Apps that are not regularly maintained may experience compatibility issues or security vulnerabilities over time.

Application analytics are implemented as specified in your service agreement to track usage, performance, and user behavior. Analytics data is provided to you and used to inform maintenance and improvement recommendations.'
                ]
            ]
        ],
        [
            'icon' => 'alert',
            'title' => 'Limitations of Liability',
            'description' => 'Legal limitations on our liability and responsibility for service outcomes.',
            'content' => [
                [
                    'subtitle' => 'Service Performance and Results',
                    'details' => 'While we apply professional expertise and best practices in all our services, we cannot guarantee specific outcomes or results. Digital marketing results, learning outcomes, and application performance are influenced by numerous external factors beyond our control.

For marketing services, results are subject to platform algorithm changes, market competition, industry trends, and consumer behavior. We do not guarantee specific rankings, engagement rates, conversion rates, or return on investment.

For educational services, learning outcomes depend significantly on student participation, effort, and aptitude. We provide quality instruction and support but cannot guarantee specific skill levels, certification outcomes, or employment results.

For technical services, while we develop and test thoroughly, we cannot guarantee completely error-free performance across all possible scenarios, devices, or future platform changes.'
                ],
                [
                    'subtitle' => 'Limitation of Liability Cap',
                    'details' => 'Our total liability for any claims related to our services shall not exceed the amount paid by you for the specific services that gave rise to the claim during the 6 months preceding the claim, regardless of the form of action, whether in contract, tort, or otherwise.

For educational programs, our liability is limited to providing replacement services or refunding program fees according to our refund policy, and does not extend to consequential outcomes such as career advancement or employment opportunities.

For development services, our liability is limited to correcting defects covered under warranty periods or refunding development fees for uncorrectable defects, and does not extend to business losses resulting from application performance.'
                ],
                [
                    'subtitle' => 'Excluded Damages',
                    'details' => 'We shall not be liable for any indirect, incidental, special, consequential, or punitive damages resulting from your use of our services, including but not limited to:

• Loss of profits, revenue, business opportunities, anticipated savings, or goodwill
• Business interruption or downtime
• Loss or corruption of data or information
• Cost of procurement of substitute goods or services
• Any damages resulting from third-party claims against you

These limitations apply even if we have been advised of the possibility of such damages and regardless of the form of action, whether in contract, tort, or any other legal theory.

In jurisdictions that do not allow the exclusion or limitation of liability for consequential or incidental damages, our liability shall be limited to the maximum extent permitted by law.'
                ]
            ]
        ],
        [
            'icon' => 'refresh',
            'title' => 'Cancellation & Refunds',
            'description' => 'Policies regarding service cancellation, termination, and refund procedures.',
            'content' => [
                [
                    'subtitle' => 'Service Cancellation Terms',
                    'details' => 'For ongoing services, either party may terminate with written notice as specified in the service agreement, typically 30 days. One-time projects may have different cancellation terms as outlined in project agreements.

For educational programs, cancellation policies vary based on program type and duration:
• Cancellations before program commencement may receive full or partial refunds as specified in program terms
• Cancellations after program commencement are subject to the refund policies outlined below

When you cancel services, you remain responsible for payment of services rendered up to the effective cancellation date. Early termination of fixed-term contracts may incur early termination fees as specified in your service agreement.

We reserve the right to terminate services immediately for material breach of these terms, non-payment, or when continuing to provide services would put us at legal risk or significant reputational harm.'
                ],
                [
                    'subtitle' => 'Refund Policies',
                    'details' => 'Refund policies vary by service type:

For digital marketing services:
• Ongoing services: No refunds for services already delivered
• One-time services: Partial refunds may be available for incomplete deliverables according to percentage of completion

For educational programs:
• Full refunds are available only during specified cooling-off periods, typically 7 days after enrollment and before accessing course materials
• Partial refunds may be available within the first 25% of the program duration, with deductions for services already delivered
• No refunds are typically available after 25% of program completion

For development projects:
• Milestone-based payments are non-refundable upon approval of milestone deliverables
• Deposits and initial payments are typically non-refundable as they secure project scheduling

All refund requests must be submitted in writing with explanation of the reasons for the request. Refund processing typically takes 7-14 business days depending on payment method.'
                ],
                [
                    'subtitle' => 'Deliverables and Work Product',
                    'details' => 'Upon service termination or cancellation, the following applies to work product and deliverables:

• Completed and paid-for deliverables remain your property according to the intellectual property terms
• Partially completed work that has not been paid for remains our property
• Materials created specifically for your projects will be provided in their current state upon full payment of services rendered

For educational programs, upon cancellation:
• Access to learning platforms, materials, and sessions will be terminated on the effective cancellation date
• Completed assignments and assessments will remain in our records according to our data retention policies
• Certificates will only be issued if all program requirements were completed prior to cancellation

For development projects, upon cancellation:
• Source code and assets for completed and paid milestones will be provided as specified in the service agreement
• Development environments and staging servers will be maintained for a limited period (typically 14 days) to facilitate transition
• Documentation for completed work will be provided in its current state'
                ]
            ]
        ],
        [
            'icon' => 'ban',
            'title' => 'Prohibited Uses',
            'description' => 'Activities and uses that are not permitted when using our services.',
            'content' => [
                [
                    'subtitle' => 'Illegal Activities and Content',
                    'details' => 'You may not use our services for any illegal purposes or to promote illegal activities. This includes but is not limited to:

• Fraud, phishing, or deceptive business practices
• Money laundering or terrorist financing
• Violation of intellectual property rights or confidentiality obligations
• Violations of consumer protection laws or advertising regulations
• Activities that violate export controls or economic sanctions

Content that promotes, encourages, or provides instructions for illegal activities is strictly prohibited across all our services, including educational programs, marketing services, and development projects.

We reserve the right to terminate services immediately and without refund if we have reasonable belief that our services are being used for illegal purposes. We may also report such activities to appropriate authorities.'
                ],
                [
                    'subtitle' => 'Prohibited Content Categories',
                    'details' => 'We do not provide services for content or businesses in the following categories:

• Adult or pornographic content
• Content promoting violence, hatred, or discrimination
• Content that is defamatory, harassing, or invades privacy
• Content that exploits or endangers children
• Weapons, firearms, or ammunition
• Counterfeit products or services
• Gambling services (unless properly licensed and approved in advance)
• Sale of tobacco, vaping products, or illegal substances

For educational programs, discussions of these topics may be permitted in appropriate academic contexts with proper framing and educational purpose, but creation of such content is not permitted in projects or assignments.

If your business operates in a regulated or sensitive industry not listed above, you must disclose this during the service inquiry phase so we can determine if we can appropriately serve your needs within our ethical guidelines.'
                ],
                [
                    'subtitle' => 'Platform Policy Violations',
                    'details' => 'You may not request or instruct us to implement tactics that violate the terms of service or policies of platforms we work with, including but not limited to:

• Social media platform community guidelines and advertising policies
• Search engine webmaster guidelines
• App store developer policies
• Email marketing and anti-spam regulations
• Online marketplace seller policies

Prohibited tactics include:
• Artificial engagement (bots, engagement pods, fake accounts)
• Keyword stuffing or hidden text in SEO
• Misleading advertising claims or false testimonials
• Incentivized reviews where prohibited
• Email list purchasing or non-consensual marketing
• App store or review manipulation

We maintain ethical standards in all digital marketing and development practices. We will decline requests to implement tactics that violate platform policies or industry ethical standards, even if competitors may be using such tactics.'
                ]
            ]
        ],
        [
            'icon' => 'scale',
            'title' => 'Dispute Resolution',
            'description' => 'How conflicts and disputes will be handled and resolved.',
            'content' => [
                [
                    'subtitle' => 'Governing Law and Jurisdiction',
                    'details' => 'These terms shall be governed by and construed in accordance with the laws of India, without regard to its conflict of law principles. Any disputes arising from these terms or our services shall be subject to the exclusive jurisdiction of the courts in Mumbai, Maharashtra, India.

For international clients, these governing law provisions apply regardless of your location, unless specifically modified in a separate written agreement. By using our services, you consent to the jurisdiction of Indian courts for dispute resolution purposes.

Nothing in these terms shall prevent us from seeking injunctive relief in any jurisdiction when necessary to protect our intellectual property rights or prevent irreparable harm.'
                ],
                [
                    'subtitle' => 'Informal Dispute Resolution',
                    'details' => 'Before initiating formal legal proceedings, both parties agree to attempt resolution through good faith negotiations. The disputing party shall send a written notice describing the issue and desired resolution to the other party.

Upon receiving a dispute notice, we will:
• Acknowledge receipt within 5 business days
• Investigate the matter thoroughly
• Provide a written response within 15 business days outlining our position and proposed resolution

If the dispute remains unresolved after this initial exchange, the parties agree to escalate the matter to senior management on both sides for further discussion and resolution attempts before proceeding to formal mediation or legal action.'
                ],
                [
                    'subtitle' => 'Mediation and Arbitration',
                    'details' => 'If informal negotiations fail to resolve a dispute within 30 days, the parties agree to participate in mediation with a mutually agreed-upon mediator. Mediation costs shall be shared equally unless otherwise agreed.

If mediation is unsuccessful, disputes shall be resolved through binding arbitration in accordance with the Arbitration Rules of the Mumbai Centre for International Arbitration. The arbitration shall be conducted in Mumbai, India, in the English language, by a single arbitrator jointly selected by both parties.

The arbitrator shall have the authority to grant any remedy or relief that would be available in court, but shall not have the authority to award punitive or exemplary damages. The arbitrator\'s decision shall be final and binding on both parties.

Notwithstanding the foregoing, either party may seek injunctive relief in any court of competent jurisdiction to prevent imminent harm or preserve the status quo pending resolution of the dispute.'
                ]
            ]
        ],
        [
            'icon' => 'gavel',
            'title' => 'Intellectual Property',
            'description' => 'Rights and ownership of content, designs, code, and other intellectual property.',
            'content' => [
                [
                    'subtitle' => 'Ownership of Deliverables',
                    'details' => 'Upon full payment for our services, you receive ownership rights to final deliverables as follows:

For marketing and design services:
• Final approved designs, graphics, and marketing materials created specifically for you
• Final approved content created specifically for your campaigns

For development services:
• Custom code developed specifically for your project
• Custom user interface elements created specifically for your application

Ownership transfer does not include:
• Draft or unused concepts, designs, or code
• Our proprietary tools, processes, or methodologies
• Third-party elements such as stock photos, fonts, plugins, or libraries

For clarity, we transfer rights only to final deliverables, not to the knowledge, techniques, or processes used to create them. The specific scope of rights transferred may be further defined in your service agreement.'
                ],
                [
                    'subtitle' => 'Educational Materials and Content',
                    'details' => 'All course materials, presentations, videos, assignments, and educational content provided through our programs remain our exclusive intellectual property. When you enroll in our programs, you receive a limited, non-exclusive license to:

• Access and use the materials for your personal educational purposes
• Complete and submit assignments and projects as part of the program
• Download and store materials for personal reference (unless specifically restricted)

You may not:
• Share, distribute, or publish course materials with non-enrolled individuals
• Use course materials for commercial purposes or to create competing offerings
• Remove copyright notices or attribution from any materials

Projects and assignments you complete as part of educational programs remain your intellectual property, though we may request permission to use them as examples for promotional purposes.'
                ],
                [
                    'subtitle' => 'Portfolio Rights and Attribution',
                    'details' => 'Unless explicitly specified otherwise in writing, we retain the right to:

• Display your final deliverables in our portfolio, case studies, and promotional materials
• Describe the services provided to you in our marketing materials
• Use non-confidential project outcomes in presentations or educational contexts

We will exercise these rights respectfully, focusing on our contribution rather than sensitive business information. If you require confidentiality or restricted portfolio usage, this must be negotiated before project commencement.

For appropriate projects, we may request to place a discreet credit or link (e.g., "Website designed by Padak") on the deliverable. This is optional and subject to your approval.

You agree not to remove any copyright notices, attributions, or watermarks that may be placed on preliminary concepts or drafts shared during the development process.'
                ]
            ]
        ]
    ];

    function getIconSVG($iconName) {
        $icons = [
            'handshake' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4m-7 0h14"/></svg>',
            'users' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>',
            'book' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>',
            'credit-card' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>',
            'shield' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>',
            'code' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>',
            'palette' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/></svg>',
            'share' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>',
            'smartphone' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>',
            'alert' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>',
            'refresh' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>',
            'ban' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>',
            'scale' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/></svg>',
            'gavel' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/></svg>'
        ];
        return isset($icons[$iconName]) ? $icons[$iconName] : $icons['handshake'];
    }
    ?>

    <!-- Header Section -->
    <section class="header-section">
        <div class="container">
            <div class="header-content">
                <div class="header-icon-wrapper">
                    <div class="header-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <div>
                        <h1>
                            Terms & <span class="gradient-text">Conditions</span>
                        </h1>
                    </div>
                </div>
                <p class="header-description">
                    These terms and conditions outline the rules and regulations for using Padak's 
                    services, including courses, internships, digital marketing, and development services.
                </p>
                <div class="header-badges">
                    <div class="badge badge-green">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span>Last Updated: February 2026</span>
                    </div>
                    <div class="badge badge-blue">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span>Effective Immediately</span>
                    </div>
                    <div class="badge badge-purple">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span>Applicable Worldwide</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="bg-float bg-float-1"></div>
        <div class="bg-float bg-float-2"></div>
    </section>

    <!-- Terms Sections -->
    <section class="terms-section">
        <div class="container">
            <div class="terms-container">
                <?php foreach ($termsData as $index => $section): ?>
                <div class="term-card">
                    <div class="card-header" onclick="toggleSection(<?php echo $index; ?>)">
                        <div class="card-icon">
                            <?php echo getIconSVG($section['icon']); ?>
                        </div>
                        <div class="card-text">
                            <h3 class="card-title"><?php echo htmlspecialchars($section['title']); ?></h3>
                            <p class="card-description"><?php echo htmlspecialchars($section['description']); ?></p>
                        </div>
                        <button class="toggle-button" id="toggle-<?php echo $index; ?>" type="button" onclick="event.stopPropagation(); toggleSection(<?php echo $index; ?>)">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                    </div>
                    
                    <div class="card-content" id="content-<?php echo $index; ?>">
                        <div class="content-inner">
                            <?php foreach ($section['content'] as $item): ?>
                            <div class="content-item">
                                <h4 class="content-subtitle">
                                    <div class="bullet-dot"></div>
                                    <?php echo htmlspecialchars($item['subtitle']); ?>
                                </h4>
                                <div class="content-details">
                                    <?php echo htmlspecialchars($item['details']); ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Notice Section -->
    <section class="notice-section">
        <div class="container">
            <div class="notice-card">
                <div class="notice-content">
                    <div class="notice-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                    <div class="notice-text">
                        <h3>Important Notice</h3>
                        <p>
                            These terms and conditions are legally binding. By using our services, enrolling in our courses, 
                            or participating in our programs, you agree to be bound by these terms. If you do not agree with 
                            any part of these terms, please do not use our services. For questions or clarifications, please 
                            contact our legal team before proceeding.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section class="contact-section">
        <div class="container">
            <div class="contact-content">
                <div class="contact-header">
                    <div class="contact-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                        </svg>
                    </div>
                    <h2>
                        Legal <span class="gradient-text">Questions?</span>
                    </h2>
                </div>
                <p class="contact-description">
                    If you have any questions about these Terms and Conditions or need clarification 
                    on any provisions, our legal team is here to help.
                </p>
                <div class="button-group">
                    <a href="mailto:contact@thepadak.com" class="btn btn-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                        </svg>
                        Contact Legal Team
                    </a>
                    <button class="btn btn-outline" onclick="window.print()">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Download Terms
                    </button>
                </div>
            </div>
        </div>
    </section>

    <script>
        function toggleSection(index) {
            const content = document.getElementById('content-' + index);
            const button = document.getElementById('toggle-' + index);
            
            if (content.classList.contains('expanded')) {
                content.classList.remove('expanded');
                button.classList.remove('active');
            } else {
                content.classList.add('expanded');
                button.classList.add('active');
            }
        }
    </script>
</body>
</html>