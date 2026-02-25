<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy - Padak</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --orange-500: #f97316;
            --orange-400: #fb923c;
            --orange-600: #ea580c;
            --orange-50: #fff7ed;
            --orange-100: #ffedd5;
            --bg-primary: #ffffff;
            --bg-secondary: #f9fafb;
            --text-primary: #111827;
            --text-secondary: #6b7280;
            --border-color: #e5e7eb;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1);
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1);
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: var(--text-primary);
            background: linear-gradient(135deg, rgba(255, 247, 237, 0.5) 0%, var(--bg-primary) 50%, rgba(255, 237, 213, 0.3) 100%);
            min-height: 100vh;
            position: relative;
        }

        .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        @media (min-width: 640px) {
            .container {
                padding: 0 1.5rem;
            }
        }

        @media (min-width: 1024px) {
            .container {
                padding: 0 2rem;
            }
        }

        /* Header Section */
        .header-section {
            padding: 4rem 0;
            position: relative;
            overflow: hidden;
        }

        .header-content {
            text-align: center;
            margin-bottom: 3rem;
        }

        .header-icon-wrapper {
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }

        .header-icon {
            width: 4rem;
            height: 4rem;
            background: linear-gradient(135deg, var(--orange-500), var(--orange-400));
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: var(--shadow-lg);
        }

        .header-icon svg {
            width: 2rem;
            height: 2rem;
            color: white;
        }

        .header-title {
            font-size: 2.25rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        @media (min-width: 1024px) {
            .header-title {
                font-size: 3rem;
            }
        }

        .gradient-text {
            background: linear-gradient(to right, var(--orange-500), var(--orange-400));
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .header-description {
            font-size: 1.25rem;
            color: var(--text-secondary);
            max-width: 48rem;
            margin: 0 auto 2rem;
        }

        .header-meta {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .meta-icon {
            width: 1rem;
            height: 1rem;
        }

        .icon-green { color: #22c55e; }
        .icon-blue { color: #3b82f6; }
        .icon-orange { color: var(--orange-500); }

        /* Floating Background Elements */
        .bg-float-1 {
            position: absolute;
            top: 5rem;
            left: 2.5rem;
            width: 8rem;
            height: 8rem;
            background: rgba(251, 146, 60, 0.1);
            border-radius: 50%;
            filter: blur(3rem);
            animation: pulse 3s ease-in-out infinite;
        }

        .bg-float-2 {
            position: absolute;
            bottom: 5rem;
            right: 2.5rem;
            width: 6rem;
            height: 6rem;
            background: rgba(249, 115, 22, 0.1);
            border-radius: 50%;
            filter: blur(2rem);
            animation: pulse 3s ease-in-out infinite 1s;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        /* Privacy Sections */
        .privacy-sections {
            padding: 4rem 0;
        }

        .sections-wrapper {
            max-width: 56rem;
            margin: 0 auto;
        }

        .privacy-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-md);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            border: 1px solid transparent;
        }

        .privacy-card:hover {
            box-shadow: var(--shadow-xl);
            background: white;
            border-color: rgba(249, 115, 22, 0.1);
        }

        .card-accent {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 0.25rem;
            background: linear-gradient(to right, var(--orange-500), var(--orange-400));
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .privacy-card:hover .card-accent {
            transform: scaleX(1);
        }

        .card-header {
            padding: 1.5rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .card-icon-wrapper {
            width: 3rem;
            height: 3rem;
            background: linear-gradient(135deg, var(--orange-500), var(--orange-400));
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: var(--shadow-lg);
            transition: all 0.3s ease;
        }

        .privacy-card:hover .card-icon-wrapper {
            transform: scale(1.1) rotate(3deg);
        }

        .card-icon-wrapper svg {
            width: 1.5rem;
            height: 1.5rem;
            color: white;
        }

        .card-header-content {
            flex: 1;
            min-width: 0;
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
            transition: color 0.3s ease;
        }

        .privacy-card:hover .card-title {
            color: var(--orange-600);
        }

        .card-description {
            font-size: 1rem;
            color: var(--text-secondary);
            line-height: 1.5;
        }

        .toggle-button {
            background: none;
            border: none;
            color: var(--orange-500);
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 0.375rem;
            transition: all 0.2s ease;
            flex-shrink: 0;
        }

        .toggle-button:hover {
            background: rgba(249, 115, 22, 0.1);
            color: var(--orange-600);
        }

        .toggle-button svg {
            width: 1.25rem;
            height: 1.25rem;
        }

        .card-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
            border-top: 1px solid transparent;
        }

        .card-content.expanded {
            max-height: none;
            border-top-color: rgba(249, 115, 22, 0.1);
        }

        .card-content-inner {
            padding: 1.5rem;
        }

        .content-item {
            margin-bottom: 1.5rem;
        }

        .content-item:last-child {
            margin-bottom: 0;
        }

        .content-subtitle {
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.75rem;
        }

        .bullet-dot {
            width: 0.5rem;
            height: 0.5rem;
            background: linear-gradient(to right, var(--orange-500), var(--orange-400));
            border-radius: 50%;
        }

        .content-details {
            color: var(--text-secondary);
            line-height: 1.75;
            padding-left: 1rem;
        }

        .card-bg-pattern {
            position: absolute;
            bottom: -0.5rem;
            right: -0.5rem;
            width: 5rem;
            height: 5rem;
            background: rgba(249, 115, 22, 0.05);
            border-radius: 50%;
            filter: blur(2rem);
            transition: background 0.3s ease;
        }

        .privacy-card:hover .card-bg-pattern {
            background: rgba(249, 115, 22, 0.1);
        }

        /* Contact Section */
        .contact-section {
            padding: 4rem 0;
            background: linear-gradient(to right, rgba(249, 115, 22, 0.1), rgba(251, 146, 60, 0.1));
            position: relative;
        }

        .contact-content {
            max-width: 48rem;
            margin: 0 auto;
            text-align: center;
        }

        .contact-icon-wrapper {
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }

        .contact-icon {
            width: 3.5rem;
            height: 3.5rem;
            background: linear-gradient(135deg, var(--orange-500), var(--orange-400));
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: var(--shadow-lg);
        }

        .contact-icon svg {
            width: 1.75rem;
            height: 1.75rem;
            color: white;
        }

        .contact-title {
            font-size: 1.875rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .contact-description {
            font-size: 1.25rem;
            color: var(--text-secondary);
            margin-bottom: 2rem;
        }

        .contact-buttons {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            justify-content: center;
        }

        @media (min-width: 640px) {
            .contact-buttons {
                flex-direction: row;
            }
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            font-size: 1rem;
            font-weight: 500;
            border-radius: 0.5rem;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .button-primary {
            background: linear-gradient(to right, var(--orange-500), var(--orange-400));
            color: white;
            box-shadow: var(--shadow-lg);
        }

        .button-primary:hover {
            background: linear-gradient(to right, var(--orange-600), var(--orange-500));
            box-shadow: var(--shadow-xl);
            transform: translateY(-2px);
        }

        .button-outline {
            border: 2px solid var(--orange-500);
            color: var(--orange-600);
            background: transparent;
        }

        .button-outline:hover {
            background: var(--orange-500);
            color: white;
        }

        .button svg {
            width: 1.25rem;
            height: 1.25rem;
        }

        /* Responsive adjustments */
        @media (max-width: 640px) {
            .header-section {
                padding: 2rem 0;
            }

            .header-title {
                font-size: 1.875rem;
            }

            .header-description {
                font-size: 1rem;
            }

            .privacy-sections {
                padding: 2rem 0;
            }

            .contact-section {
                padding: 2rem 0;
            }

            .contact-title {
                font-size: 1.5rem;
            }

            .card-header {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>
    <!-- Header Section -->
    <section class="header-section">
        <div class="container">
            <div class="header-content">
                <div class="header-icon-wrapper">
                    <div class="header-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                    </div>
                    <div>
                        <h1 class="header-title">
                            Privacy <span class="gradient-text">Policy</span>
                        </h1>
                    </div>
                </div>
                <p class="header-description">
                    At Padak, your branding partner, we are committed to protecting your privacy and ensuring 
                    the security of your personal information while delivering our educational and digital services.
                </p>
                <div class="header-meta">
                    <div class="meta-item">
                        <svg class="meta-icon icon-green" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>Last Updated: August 2025</span>
                    </div>
                    <div class="meta-item">
                        <svg class="meta-icon icon-blue" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>Applies Globally</span>
                    </div>
                    <div class="meta-item">
                        <svg class="meta-icon icon-orange" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <span>Compliant with Data Protection Regulations</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Floating background elements -->
        <div class="bg-float-1"></div>
        <div class="bg-float-2"></div>
    </section>

    <!-- Privacy Sections -->
    <section class="privacy-sections">
        <div class="container">
            <div class="sections-wrapper">
                <?php
                $privacySections = [
                    [
                        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />',
                        'title' => 'Information We Collect',
                        'description' => 'Personal and educational information we collect when you use our services.',
                        'content' => [
                            [
                                'subtitle' => 'Personal Identification Information',
                                'details' => 'We collect personal information necessary for our services, including but not limited to your full legal name, email address, phone number, physical address, date of birth, nationality, government-issued identification (when required for certification verification purposes), profile photos, educational history, and professional background. This information is collected during account creation, course enrollment, internship applications, service requests, or when you otherwise interact with our services. We may collect this information through our websites, mobile applications, enrollment forms, contracts, or communications with our representatives.'
                            ],
                            [
                                'subtitle' => 'Educational and Performance Data',
                                'details' => 'For students enrolled in our courses and internships, we collect comprehensive educational data including but not limited to attendance records, assignment submissions, grades and assessment scores, feedback on work, participation metrics in online sessions, progress reports, completed projects, skills assessments, peer review inputs, internship performance evaluations, and certification status. This information is essential for providing our educational services, monitoring student progress, and issuing valid certifications. We may also collect information about your learning preferences, pace, and areas where you may need additional support to personalize your educational experience.'
                            ],
                            [
                                'subtitle' => 'Account and Authentication Information',
                                'details' => 'When you create or maintain an account on our platforms or services, we collect and process account credentials (username, password hashes, security questions/answers), account preferences, account activity logs (including login times, session duration, features used), notification settings, and subscription preferences. We may also collect multi-factor authentication details when enabled. This information is necessary to secure your account, prevent unauthorized access, personalize your experience, and investigate potential misuse of our services. For security purposes, we may also log IP addresses, device identifiers, and geolocation data associated with account activities.'
                            ],
                            [
                                'subtitle' => 'Business and Service-Specific Information',
                                'details' => 'When providing business services such as digital marketing, web development, or social media management, we collect information relevant to these services. This may include business details (company name, industry, business registration information), website credentials and access keys (when authorized for services like hosting or development), social media account access (when authorized for management services), business objectives and KPIs, target audience information, brand guidelines, marketing preferences, design assets, content requirements, competitive analysis information, and performance analytics. For web and app development services, we collect technical requirements, hosting preferences, domain information, user experience expectations, and functional specifications.'
                            ],
                            [
                                'subtitle' => 'Payment and Financial Information',
                                'details' => 'To process payments for our services, we collect payment information which may include credit/debit card details, bank account information, billing addresses, payment histories, invoicing preferences, and transaction records. Financial information is processed in compliance with applicable payment card industry standards (PCI DSS) and financial regulations. While we store transaction records for accounting and legal purposes, full payment details are typically processed through secure third-party payment processors and not stored directly on our systems.'
                            ],
                            [
                                'subtitle' => 'Technical and Usage Information',
                                'details' => 'We automatically collect technical data when you interact with our websites, applications, and online learning platforms. This includes IP addresses, browser type and version, operating system, device information, screen resolution, language preferences, referring/exit pages, clickstream data, pages visited, time spent on pages, features used, actions taken within our platforms, error logs, crash reports, and unique device identifiers. We use this information to ensure proper functioning of our services, troubleshoot technical issues, analyze usage patterns, improve user experience, and secure our systems. Collection occurs through server logs, cookies, pixels, web beacons, and similar technologies.'
                            ],
                            [
                                'subtitle' => 'Communications and Feedback',
                                'details' => 'We collect and store communications you have with us, including email correspondence, chat logs, support tickets, phone call recordings (with notice), survey responses, testimonials, feedback forms, and customer service interactions. This information helps us respond to your inquiries, resolve issues, improve our services based on feedback, train our staff, maintain records of our communications for quality assurance, and document service requests or changes to your account.'
                            ],
                            [
                                'subtitle' => 'Third-Party Platform Information',
                                'details' => 'When you interact with our content on third-party platforms (such as social media networks) or access our services through third-party integrations (such as single sign-on), we may collect information from these platforms in accordance with their privacy settings and your permissions. This may include profile information, engagement metrics, social connections, and other data made available through these platforms\' APIs. Similarly, when using third-party tools integrated into our educational offerings (such as coding environments, design tools, or digital marketing platforms), information may be collected through these integrations.'
                            ]
                        ]
                    ],
                    [
                        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222" />',
                        'title' => 'Educational Services Data',
                        'description' => 'How we handle information related to our courses and internships.',
                        'content' => [
                            [
                                'subtitle' => 'Online Learning Environment Data',
                                'details' => 'Our educational platforms collect detailed data about your learning activities to facilitate effective education. This includes but is not limited to course progress indicators, module completion status, quiz and assessment results, time spent on various learning materials, interactive exercise responses, discussion forum contributions, peer interactions, project submissions, and personalized learning pathways. We analyze this data to identify learning patterns, adapt course difficulty, provide timely interventions when students face challenges, and continuously improve our educational content. This processing is fundamental to our educational mission and contractually necessary to provide you with quality instruction and support.'
                            ],
                            [
                                'subtitle' => 'Virtual Classroom and Meeting Recordings',
                                'details' => 'We record online learning sessions, workshops, and virtual classrooms conducted via platforms such as Google Meet, Zoom, Microsoft Teams, or other video conferencing tools. These recordings capture audio, video, shared screens, chat messages, interactive whiteboard content, polling responses, breakout room sessions, and participant engagement metrics. Recordings serve multiple educational purposes including: allowing students to review sessions they attended, providing access to students who missed live sessions, creating instructional resources, training our educators, and quality assurance. Before recording begins, clear notification is provided to all participants. If you have concerns about appearing in recordings, you may disable your camera, use a virtual background, or contact your instructor for accommodations. Recordings are stored securely with access restricted to enrolled students and authorized staff. We maintain these recordings for a defined period (typically the duration of the course plus one year) before secure deletion.'
                            ],
                            [
                                'subtitle' => 'Certification and Credential Information',
                                'details' => 'To create, issue, and verify legitimate certificates and credentials, we collect and process comprehensive certification data. This includes your full legal name (as you wish it to appear on credentials), unique student identifier, course or program details, completion date, achievement level, assessment scores, competencies demonstrated, accreditation information, digital signature verification data, and credential expiration dates (if applicable). We maintain a secure database of all issued credentials to verify authenticity when employers or other institutions request verification. This verification system helps protect the value of your earned credentials by preventing forgery. For certain professional certifications, we may be required to share completion information with accrediting bodies or regulatory authorities. Certificate data is maintained for extended periods (typically 7-10 years) to support long-term verification needs for your professional advancement.'
                            ],
                            [
                                'subtitle' => 'Internship and Placement Data',
                                'details' => 'For students in internship programs, we process extensive data to facilitate appropriate placements and evaluate performance. This includes detailed resume information, skills assessments, portfolio samples, work preferences, geographical constraints, professional interests, career objectives, interview performance notes, placement history, supervisor evaluations, attendance records, project contributions, skill development progress, and professional recommendation information. We may share relevant portions of this data with potential host companies or placement partners (with your explicit consent) to facilitate internship matching. During internships, we collect regular progress reports, performance evaluations, and feedback from both interns and supervisors to monitor the quality of the experience, address any issues, and assess learning outcomes. This data helps us improve our internship programs, provide accurate references, and develop better placement strategies for future students.'
                            ]
                        ]
                    ],
                    [
                        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" />',
                        'title' => 'How We Use Your Information',
                        'description' => 'The purposes for which we process your personal information.',
                        'content' => [
                            [
                                'subtitle' => 'Delivering Educational Services and Programs',
                                'details' => 'We use your personal and educational information to deliver comprehensive educational services including course instruction, internship coordination, skills assessment, personalized feedback, progress tracking, and certification. This processing is necessary to fulfill our contractual obligations to you as a student or participant. Specifically, we use your data to create and maintain your student profile, grant appropriate access to learning platforms and materials, track your educational progress, provide timely feedback on assignments and assessments, identify and address learning challenges, customize learning pathways based on your progress and needs, administer examinations, verify your identity for academic integrity, issue legitimate certifications upon completion, and maintain accurate academic records.'
                            ],
                            [
                                'subtitle' => 'Providing Digital Marketing and Business Services',
                                'details' => 'For clients using our professional services such as digital marketing, web development, and social media management, we process your information to create, implement, manage, and optimize your digital presence and marketing strategies. This includes using your business information to develop targeted marketing campaigns, create and maintain websites or applications according to your specifications, manage social media accounts on your behalf, implement SEO strategies, conduct market research, analyze campaign performance, generate performance reports, make data-driven recommendations for optimization, implement changes based on analytics, and maintain your digital assets.'
                            ],
                            [
                                'subtitle' => 'Account Management and Service Administration',
                                'details' => 'We use your information for essential account management and administrative functions including creating and maintaining your user accounts, authenticating your identity when you log in, processing your payments and managing billing, generating invoices and receipts, tracking service usage entitlements, implementing your account preferences, sending service notifications about maintenance or updates, responding to account-related inquiries, troubleshooting technical issues, preventing and detecting fraudulent account activities, enforcing our terms of service, and facilitating secure account recovery procedures when needed.'
                            ],
                            [
                                'subtitle' => 'Communication and Support',
                                'details' => 'We use your contact information to facilitate essential communications related to your enrollment, account, and services. This includes sending course announcements and updates, distributing assignment feedback and grades, providing technical support and assistance, responding to your inquiries and requests, sending service-related notifications, delivering administrative information about policy updates or maintenance, issuing payment receipts and invoices, and scheduling appointments or sessions.'
                            ]
                        ]
                    ],
                    [
                        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />',
                        'title' => 'Information Sharing',
                        'description' => 'How and when we share your information with third parties.',
                        'content' => [
                            [
                                'subtitle' => 'Educational Partners and Instructors',
                                'details' => 'We share relevant student information with authorized educational partners and instructors directly involved in delivering our educational programs. This includes sharing enrollment information, academic records, assignment submissions, assessment results, attendance data, participation metrics, and learning progress with instructors, teaching assistants, mentors, and academic advisors who need this information to provide instruction, assessment, feedback, and support. For accredited programs, we may share required information with accrediting institutions to validate course completion and certification.'
                            ],
                            [
                                'subtitle' => 'Service Providers and Technology Partners',
                                'details' => 'We engage various service providers and technology partners who perform essential functions for our operations. These include learning management system providers, video conferencing platforms, cloud storage services, database management services, payment processors, email service providers, customer relationship management tools, analytics platforms, hosting services, technical support vendors, student information systems, authentication services, messaging platforms, and security service providers. These third parties may process personal information on our behalf to perform their functions, but are contractually prohibited from using the information for other purposes.'
                            ],
                            [
                                'subtitle' => 'Legal and Regulatory Authorities',
                                'details' => 'We may disclose personal information when required by law, regulation, legal process, or governmental request. This includes responding to court orders, subpoenas, or other legal processes; complying with regulatory audits or investigations; reporting to educational authorities as required by applicable education laws; responding to requests from law enforcement with valid warrants; reporting to tax authorities as required by tax laws; cooperating with government agencies in official investigations; and complying with other legal obligations applicable to educational and business service providers.'
                            ]
                        ]
                    ],
                    [
                        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />',
                        'title' => 'Video Conferencing & Online Sessions',
                        'description' => 'Policies regarding our online educational sessions and meetings.',
                        'content' => [
                            [
                                'subtitle' => 'Recording Purposes and Practices',
                                'details' => 'We record online educational sessions, workshops, webinars, and virtual classrooms to serve multiple legitimate educational purposes. These recordings are essential for: enabling students to review complex material at their own pace; providing access to educational content for enrolled students who were unable to attend live; creating educational resources for future reference; facilitating asynchronous learning options; allowing instructors to review sessions for self-improvement; enabling quality assurance reviews by our educational team; documenting student participation and contributions for assessment purposes; and creating archives of educational content for curriculum development. Before any recording begins, all participants receive clear notification through both verbal announcements and visual indicators within the platform interface.'
                            ],
                            [
                                'subtitle' => 'Content Captured in Recordings',
                                'details' => 'Our session recordings typically capture comprehensive content including video feeds of instructors and participating students (when cameras are enabled), audio of all speakers and discussions, shared screens and presentations, interactive whiteboard content, chat messages sent to public/group channels (private chats are not recorded), polling responses, breakout room sessions (when technically feasible), Q&A interactions, and demonstrations of software or techniques. Recordings may also include attendance information, join/leave times, engagement indicators (raised hands, reactions), and other participation metrics provided by the platform.'
                            ],
                            [
                                'subtitle' => 'Storage and Security of Recordings',
                                'details' => 'We implement comprehensive security measures to protect recorded educational content. All recordings are stored in secure, access-controlled systems with appropriate encryption during both transmission and storage. Access to recordings is strictly limited to: enrolled students in the specific course; instructors and teaching assistants assigned to the course; authorized educational staff with legitimate need-to-access for quality assurance or support purposes; and technical administrators with specific responsibilities related to platform management.'
                            ]
                        ]
                    ],
                    [
                        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />',
                        'title' => 'Data Security',
                        'description' => 'Measures we take to protect your information from unauthorized access.',
                        'content' => [
                            [
                                'subtitle' => 'Technical Security Infrastructure',
                                'details' => 'We implement comprehensive technical security measures designed to protect your personal and educational information throughout its lifecycle in our systems. These include: industry-standard encryption for data in transit using TLS/SSL protocols; strong encryption for sensitive data at rest using AES-256 or equivalent standards; secure, access-controlled data centers with environmental and physical safeguards; regular security patching and system updates; network security controls including firewalls, intrusion detection systems, and traffic monitoring; regular vulnerability scanning and penetration testing by both automated tools and security professionals; secure development practices for our applications and platforms; database security controls including query restrictions and parameterized queries to prevent injection attacks; anti-malware protection across all systems; secure backup procedures with encrypted backup media; and disaster recovery capabilities to ensure data availability.'
                            ],
                            [
                                'subtitle' => 'Access Controls and Authentication',
                                'details' => 'We maintain strict access control mechanisms to ensure information is only accessible to authorized individuals with legitimate need-to-access. Our multi-layered approach includes: role-based access controls that limit system access based on job responsibilities; principle of least privilege implementation where staff members only have access to the minimum data necessary for their specific functions; strong password policies requiring complexity, regular changes, and prohibiting password reuse; multi-factor authentication for access to sensitive systems and administrator accounts; biometric authentication where appropriate for high-security functions; unique user IDs with detailed access logging to maintain accountability; automated timeout and lockout features to protect unattended sessions; regular access reviews to validate that access rights remain appropriate as roles change; privileged access management for administrative functions with enhanced monitoring; just-in-time access provisioning for sensitive operations; and formal access request and approval processes.'
                            ],
                            [
                                'subtitle' => 'Employee Security Measures',
                                'details' => 'Our security approach includes comprehensive human factors protection through our employee practices. All employees and contractors with access to personal information undergo: background checks appropriate to their position and access level prior to employment; formal security and privacy training during onboarding; regular refresher training on data protection, security awareness, and privacy compliance; specialized training for staff with access to sensitive information; confidentiality and data protection agreements as part of employment terms; regular security awareness communications about emerging threats and protective practices; phishing simulation exercises to maintain vigilance against social engineering; clean desk policies and secure document handling procedures; restricted physical access to areas where sensitive information is processed; and security review procedures when employees change roles or leave the organization.'
                            ]
                        ]
                    ],
                    [
                        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />',
                        'title' => 'Cookies & Tracking',
                        'description' => 'How we use cookies and similar technologies on our websites and learning platforms.',
                        'content' => [
                            [
                                'subtitle' => 'Types of Cookies We Use',
                                'details' => 'We use various types of cookies and similar technologies on our websites and educational platforms to enable functionality, enhance security, and improve user experience. Essential cookies are necessary for core website functions and security, including session management, authentication, load balancing, and basic platform functionality - these cookies cannot be disabled as they are required for services you request. Preference cookies remember your settings and choices to personalize your experience, such as language preferences, display settings, and accessibility options. Analytics cookies help us understand how visitors interact with our sites by collecting anonymized information about pages visited, time spent, user journeys, and platform performance - this data helps us improve site functionality and content relevance.'
                            ],
                            [
                                'subtitle' => 'Educational Platform Tracking',
                                'details' => 'Within our educational platforms, we use specialized tracking technologies to support effective learning and ensure academic integrity. These include technologies that: track course progress and completion status; monitor time spent on learning materials to identify engagement patterns; record assessment activities to verify academic integrity; track participation in discussions and collaborative activities; identify when students may need additional support based on engagement patterns; enable personalized learning paths based on demonstrated mastery; facilitate bookmarking and resume functionality; and support analytics that help us improve educational effectiveness.'
                            ],
                            [
                                'subtitle' => 'Consent and Control',
                                'details' => 'We respect your preferences regarding cookies and tracking technologies. When you first visit our website, you\'ll see a cookie consent banner that allows you to choose which non-essential cookies you accept. You can change these preferences at any time through our Cookie Preferences Center accessible from the site footer. Your preferences are stored in a cookie, so if you clear your cookies, you\'ll need to reset your preferences. For essential cookies that are strictly necessary for basic site functionality and security, consent is not required as the site cannot function properly without them.'
                            ]
                        ]
                    ],
                    [
                        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />',
                        'title' => 'Mobile Apps & Development',
                        'description' => 'Privacy practices related to our Android development and app publishing services.',
                        'content' => [
                            [
                                'subtitle' => 'Client App Development Services',
                                'details' => 'When developing mobile applications for clients, we process various types of information necessary for design, development, testing, and deployment. This includes client business information, application design specifications, branding assets, content for inclusion in the app, functional requirements, user experience parameters, and testing data. For apps requiring backend services, we also process database structures, API specifications, and integration requirements. During development, we implement privacy by design principles, helping clients create applications that respect user privacy through measures such as: appropriate permission management, transparent privacy notices, secure data storage methods, minimal data collection practices, secure authentication implementation, and privacy-preserving analytics configurations.'
                            ],
                            [
                                'subtitle' => 'App Store Submission and Publication',
                                'details' => 'Our app publication services involve processing information necessary to submit applications to app stores (primarily Google Play Store for Android applications) and manage their presence. This includes developer account credentials, developer identity verification information, application signing keys and certificates, app metadata (descriptions, screenshots, promotional materials), content ratings information, pricing and distribution parameters, and app versioning data. We maintain strict security protocols for handling developer credentials and signing keys, including secure storage, access limitations, and separation from general development environments.'
                            ],
                            [
                                'subtitle' => 'Mobile App Security Measures',
                                'details' => 'We implement comprehensive security measures in the mobile applications we develop to protect user data and application integrity. These include: secure coding practices throughout the development lifecycle; data encryption for sensitive information stored on devices; secure network communication using TLS/SSL; certificate pinning to prevent man-in-the-middle attacks; secure authentication implementations including biometric options where appropriate; protection against common mobile vulnerabilities; obfuscation techniques to prevent reverse engineering; secure session management; protection of application resources; secure storage of API keys and credentials; runtime application self-protection where appropriate; regular security testing specific to mobile environments; and secure implementation of third-party libraries and SDKs.'
                            ]
                        ]
                    ],
                    [
                        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />',
                        'title' => 'Social Media & Digital Marketing',
                        'description' => 'Privacy practices for our social media management and digital marketing services.',
                        'content' => [
                            [
                                'subtitle' => 'Social Media Account Management',
                                'details' => 'When providing social media management services, we require access to clients\' business social media accounts across platforms such as Facebook, Instagram, LinkedIn, Twitter, TikTok, YouTube, Pinterest, and others as relevant to the specific service agreement. This access is governed by strict security and confidentiality protocols including: secure credential management using enterprise password management systems; multi-factor authentication implementation where supported by platforms; role-based access controls through platform business management tools rather than personal login sharing where available; access limited to staff members with specific responsibilities for the account; formal offboarding procedures when staff changes occur; regular access reviews and password rotations; detailed activity logging for accountability; and confidentiality agreements covering all client account information.'
                            ],
                            [
                                'subtitle' => 'Digital Marketing Campaign Management',
                                'details' => 'Our digital marketing services involve processing data related to marketing campaigns across various platforms including search engines, social media, display networks, email marketing systems, and other digital channels. This includes: campaign configuration data; targeting parameters; audience segments; advertisement content and creative assets; bidding strategies; performance metrics; conversion tracking; A/B testing data; campaign budgets; and ROI analysis. When implementing conversion tracking and audience targeting, we follow privacy best practices including: using platform-provided privacy-enhancing features; implementing appropriate data minimization; configuring compliant cookie notices and consent mechanisms; using anonymized or aggregated data where feasible; and staying current with evolving privacy requirements for digital marketing.'
                            ],
                            [
                                'subtitle' => 'Content Creation and Management',
                                'details' => 'Our social media and marketing services include creating, managing, and publishing content on behalf of clients. This involves processing: content calendars and publishing schedules; brand guidelines and voice documentation; written copy and messaging; visual assets including photographs, graphics, and videos; audience engagement strategies; content performance analytics; content categorization and tagging; and content approval workflows. When creating content that includes personal information, testimonials, case studies, or user-generated content, we ensure appropriate rights clearances and permissions are obtained.'
                            ]
                        ]
                    ],
                    [
                        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />',
                        'title' => 'Your Rights & Choices',
                        'description' => 'Your rights regarding your personal information and how to exercise them.',
                        'content' => [
                            [
                                'subtitle' => 'Right to Access Your Information',
                                'details' => 'You have the right to request access to the personal information we hold about you. This includes the right to: obtain confirmation that we are processing your data; receive a copy of your personal information in a structured, commonly used, and machine-readable format; know the categories of personal information we collect about you; understand the purposes for which we process your information; identify recipients or categories of recipients with whom we share your information; learn the sources from which we obtained your information (except where protected by confidentiality); understand the logic involved in any automated decision-making that has a significant effect on you; and know how long we retain different categories of your data.'
                            ],
                            [
                                'subtitle' => 'Right to Correction and Completion',
                                'details' => 'You have the right to request correction of inaccurate personal information we maintain about you and to have incomplete personal information completed. This includes correcting factual errors in your contact information, account details, educational records, or other information we process. For students, this includes the right to correct inaccurate academic or enrollment information in your educational records. However, this right does not typically extend to changing subjective evaluations such as grades or assessment feedback, though most educational programs have specific academic appeals processes for assessment concerns.'
                            ],
                            [
                                'subtitle' => 'Right to Deletion',
                                'details' => 'You have the right to request deletion of your personal information in certain circumstances, subject to legal and contractual exceptions. This is sometimes called the \'right to be forgotten.\' When you request deletion, we will remove your personal information from our active systems where possible, unless retention is necessary to: comply with legal obligations; detect security incidents or protect against fraud; fix errors in functionality; enable solely internal uses aligned with your expectations; complete a transaction for which the information was collected; fulfill our contract with you; or other internal, lawful uses compatible with the context in which you provided the information.'
                            ],
                            [
                                'subtitle' => 'Consent Management',
                                'details' => 'Where we process your information based on consent, you have the right to withdraw that consent at any time, without affecting the lawfulness of processing based on consent before its withdrawal. This includes the right to: opt out of marketing communications through unsubscribe links in emails or by contacting us directly; change your cookie preferences through our Cookie Preferences Center; withdraw consent for optional data processing within our educational platforms; opt out of participation in optional research; and revoke authorizations for specific data uses that aren\'t necessary for our core services.'
                            ]
                        ]
                    ],
                    [
                        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />',
                        'title' => 'Data Retention & Deletion',
                        'description' => 'How long we keep your information and our deletion practices.',
                        'content' => [
                            [
                                'subtitle' => 'General Retention Principles',
                                'details' => 'We retain personal information for as long as necessary to fulfill the purposes for which it was collected, comply with legal obligations, resolve disputes, and enforce our agreements. Our retention practices follow key principles including: data minimization (keeping only what\'s necessary); purpose limitation (retaining data only for its original purpose unless compatible secondary use is justified); storage limitation (establishing defined retention periods); regular review of retained data; secure deletion when retention periods end; and consideration of the nature and sensitivity of different data types when setting retention periods.'
                            ],
                            [
                                'subtitle' => 'Educational Records Retention',
                                'details' => 'For students in our educational programs, we retain different types of educational records for varying periods according to their purpose and applicable requirements: Core academic records including enrollment information, completed courses, grades, and certification status are typically retained for 5-7 years after course completion to support verification requests, additional enrollments, and credential validation. Assessment details such as graded assignments, project evaluations, and detailed feedback are generally retained for 1-2 years after course completion to support academic continuity and appeals processes. Course participation data such as attendance records and engagement metrics are typically retained for 1 year after course completion.'
                            ],
                            [
                                'subtitle' => 'Secure Deletion Practices',
                                'details' => 'When retention periods expire or upon valid deletion requests, we implement secure deletion practices appropriate to the nature of the information and its storage medium. Our deletion processes include: For structured data in databases, we use secure deletion queries that permanently remove the data from active systems. For file storage systems, we implement secure file deletion methods appropriate to the storage technology. For cloud-based storage, we utilize the cloud provider\'s data deletion capabilities and verify deletion through available certifications. For backup systems, deleted data is removed from active systems immediately and naturally ages out of backups according to our backup rotation schedule (typically within 30-90 days).'
                            ]
                        ]
                    ],
                    [
                        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />',
                        'title' => 'Children\'s Privacy',
                        'description' => 'Our approach to protecting the privacy of children and young users.',
                        'content' => [
                            [
                                'subtitle' => 'Age Restrictions and Verification',
                                'details' => 'Our services are primarily designed for adult users (individuals 18 years of age or older) or older teenagers (16+) with appropriate parental involvement. For our core educational services and digital marketing offerings, we do not knowingly collect personal information from children under 16 years of age. When we do offer educational content appropriate for younger users, we implement age-appropriate design, enhanced privacy protections, and parental consent mechanisms in compliance with children\'s privacy regulations such as COPPA in the United States and similar laws in other jurisdictions.'
                            ],
                            [
                                'subtitle' => 'Parental Involvement and Consent',
                                'details' => 'For any services specifically designed for users under 16 years of age, we implement appropriate parental consent mechanisms before collecting personal information. These mechanisms vary based on the nature of the service and the age of the intended users, but may include: verifiable parental consent through credit card verification, government ID verification, signed consent forms, video conference verification, or knowledge-based verification questions; direct notice to parents about our information practices regarding children; and reasonable efforts to ensure the person providing consent is actually the child\'s parent or guardian.'
                            ]
                        ]
                    ],
                    [
                        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" />',
                        'title' => 'Legal Basis for Processing',
                        'description' => 'The legal grounds under which we process your personal information.',
                        'content' => [
                            [
                                'subtitle' => 'Contractual Necessity',
                                'details' => 'We process much of your personal information because it is necessary for the performance of a contract to which you are a party or to take steps at your request prior to entering into a contract. This includes: processing enrollment information and educational data to deliver courses you\'ve registered for; processing account information to maintain your user accounts; handling payment information to process transactions you\'ve authorized; processing client information to deliver digital marketing, web development, or other services you\'ve engaged us for; using contact information to communicate about service delivery; storing your content and submissions as required to fulfill our services; processing certification information to issue valid credentials upon completion; and maintaining records necessary to fulfill our contractual obligations.'
                            ],
                            [
                                'subtitle' => 'Legitimate Interests',
                                'details' => 'We process certain information based on our legitimate interests or those of a third party, where these interests are not overridden by your fundamental rights and freedoms. We carefully assess any potential impact on you and your rights before relying on legitimate interests. Processing activities based on legitimate interests include: analyzing service usage patterns to improve our offerings; implementing security measures to protect our systems and your information; preventing fraud and abuse of our services; conducting business analytics to understand customer needs and service performance; maintaining and enhancing our services based on user feedback and behavior analysis; limited processing for direct marketing to existing customers about similar services (subject to opt-out); using de-identified or aggregated data for research and development; protecting our legal rights and property; sharing information within our corporate group for administrative purposes; and maintaining business records required for continuity and accountability.'
                            ],
                            [
                                'subtitle' => 'Consent',
                                'details' => 'We process certain information based on your consent. We always aim to obtain specific, informed, and unambiguous consent for clearly defined purposes, and we make it easy for you to withdraw consent at any time. Processing activities based on consent typically include: sending marketing communications about our services and offerings; using cookies and similar technologies for non-essential purposes on our websites; collecting and using testimonials or success stories featuring identifiable individuals; using your information for case studies or marketing examples; sharing your information with third parties when not necessary for service delivery; processing special categories of personal data (sensitive information) when not otherwise justified; using your content or submissions for promotional purposes beyond service delivery; enrolling you in optional research studies or surveys; and enabling certain optional features within our platforms that involve additional data processing.'
                            ]
                        ]
                    ],
                    [
                        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />',
                        'title' => 'International Data Transfers',
                        'description' => 'How we handle information transfers across international borders.',
                        'content' => [
                            [
                                'subtitle' => 'Cross-Border Transfer Mechanisms',
                                'details' => 'As a globally operating educational and digital services provider, we may transfer personal information across international borders, including transfers from regions with comprehensive privacy laws to regions with different or less comprehensive protections. When transferring personal data from the European Economic Area, United Kingdom, Switzerland, or other jurisdictions with cross-border transfer restrictions, we implement appropriate safeguards to ensure adequate protection. These safeguards may include: Standard Contractual Clauses (SCCs) approved by the European Commission or UK authorities; Binding Corporate Rules for intra-group transfers (if applicable); adequacy decisions recognizing certain countries as providing adequate protection; explicit consent for specific transfers where appropriate and after informing you of the possible risks; transfers necessary for the performance of a contract between you and us or implemented at your request; transfers necessary for important reasons of public interest or legal claims; and transfers necessary to protect your vital interests when you are physically or legally incapable of giving consent.'
                            ],
                            [
                                'subtitle' => 'Service Provider Data Transfers',
                                'details' => 'When we engage service providers who may access personal information from countries different from where the data originated, we implement appropriate safeguards to protect transferred information. These include: comprehensive data processing agreements with clear processing limitations and security requirements; Standard Contractual Clauses or other approved transfer mechanisms where required by law; vendor assessment processes that evaluate privacy and security practices before engagement; contractual restrictions on sub-processing without appropriate safeguards; data minimization to limit transferred information to what is necessary; requirements for prompt return or deletion of data when processing is complete; security certifications and audit rights appropriate to the sensitivity of the data; and regular compliance verification.'
                            ]
                        ]
                    ],
                    [
                        'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />',
                        'title' => 'Policy Updates',
                        'description' => 'How we update this policy and notify you of changes.',
                        'content' => [
                            [
                                'subtitle' => 'Policy Revision Process',
                                'details' => 'We review and update this Privacy Policy periodically to ensure it accurately reflects our practices, services, and legal requirements. Our policy revision process includes: regular scheduled reviews at least annually; additional reviews when introducing new services or features with privacy implications; assessment of policy updates needed for new legal requirements or regulatory guidance; consultation with privacy professionals to ensure accuracy and comprehensiveness; stakeholder review of proposed changes; legal approval before publishing updates; and documentation of policy version history. Minor updates that don\'t significantly affect your privacy rights (such as clarifications, reorganization, or correction of errors) may be made without special notice beyond posting the updated policy.'
                            ],
                            [
                                'subtitle' => 'Notification of Material Changes',
                                'details' => 'For significant changes to this Privacy Policy that materially alter how we process your personal information, we provide advance notice through appropriate channels. These notifications may include: prominent notices on our websites and platforms before the change takes effect; direct email notifications to affected users when we have their contact information; special announcements for logged-in users through platform notifications or dashboards; temporary banners or popups highlighting key changes; blog posts or announcements explaining important updates for significant revisions; and social media notifications for our followers. The notification method depends on the nature of our relationship with you and the significance of the change.'
                            ],
                            [
                                'subtitle' => 'Policy Effective Date and Version Tracking',
                                'details' => 'This Privacy Policy is effective as of August 7, 2025. We maintain clear version tracking for all privacy policy updates, including: a visible effective date on the policy itself; internal documentation of all substantive changes between versions; an accessible change log summarizing significant updates for important revisions; archived previous versions available upon request; and distinct version numbers for tracking purposes. The effective date at the top of the policy indicates when the current version came into force. For specific questions about how our privacy practices have evolved over time or to request previous versions of our policy, please contact our privacy team.'
                            ]
                        ]
                    ]
                ];

                foreach ($privacySections as $index => $section): ?>
                    <div class="privacy-card" data-section="<?php echo $index; ?>">
                        <div class="card-accent"></div>
                        <div class="card-header" onclick="toggleSection(<?php echo $index; ?>)">
                            <div class="card-icon-wrapper">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <?php echo $section['icon']; ?>
                                </svg>
                            </div>
                            <div class="card-header-content">
                                <h3 class="card-title"><?php echo htmlspecialchars($section['title']); ?></h3>
                                <p class="card-description"><?php echo htmlspecialchars($section['description']); ?></p>
                            </div>
                            <button type="button" class="toggle-button" aria-label="Toggle section">
                                <svg class="chevron-down" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                                <svg class="chevron-up" style="display: none;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                </svg>
                            </button>
                        </div>
                        <div class="card-content">
                            <div class="card-content-inner">
                                <?php foreach ($section['content'] as $item): ?>
                                    <div class="content-item">
                                        <h4 class="content-subtitle">
                                            <span class="bullet-dot"></span>
                                            <?php echo htmlspecialchars($item['subtitle']); ?>
                                        </h4>
                                        <p class="content-details"><?php echo htmlspecialchars($item['details']); ?></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="card-bg-pattern"></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section class="contact-section">
        <div class="container">
            <div class="contact-content">
                <div class="contact-icon-wrapper">
                    <div class="contact-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <h2 class="contact-title">
                        Questions About Your <span class="gradient-text">Privacy?</span>
                    </h2>
                </div>
                <p class="contact-description">
                    If you have any questions about this Privacy Policy or how we handle your information 
                    for courses, internships, or digital services, please contact our Privacy Team.
                </p>
                <div class="contact-buttons">
                    <a href="mailto:privacy@padak.com" class="button button-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        Contact Privacy Team
                    </a>
                    <button class="button button-outline" onclick="window.print()">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Download Policy
                    </button>
                </div>
            </div>
        </div>
    </section>

    <script>
        function toggleSection(index) {
            const card = document.querySelector(`[data-section="${index}"]`);
            const content = card.querySelector('.card-content');
            const chevronDown = card.querySelector('.chevron-down');
            const chevronUp = card.querySelector('.chevron-up');
            
            if (content.classList.contains('expanded')) {
                content.classList.remove('expanded');
                content.style.maxHeight = '0';
                chevronDown.style.display = 'block';
                chevronUp.style.display = 'none';
            } else {
                content.classList.add('expanded');
                content.style.maxHeight = content.scrollHeight + 'px';
                chevronDown.style.display = 'none';
                chevronUp.style.display = 'block';
            }
        }

        // Adjust max-height on window resize for expanded sections
        window.addEventListener('resize', function() {
            document.querySelectorAll('.card-content.expanded').forEach(function(content) {
                content.style.maxHeight = content.scrollHeight + 'px';
            });
        });
    </script>
</body>
</html>