<?php
session_start();

// Set testing user session for demo (remove when login system is complete)
if (!isset($_SESSION['email'])) {
    $_SESSION['email'] = 'testing@example.com';
    $_SESSION['user'] = [
        'name' => 'Testing User',
        'email' => 'testing@example.com',
        'id' => '68a70b922f5fe'
    ];
    $_SESSION['user_id'] = '68a70b922f5fe';
    $_SESSION['logged_in'] = true;
    $_SESSION['last_activity'] = time();
}

// Get user information from session
$userName = 'Elite Member';
if (isset($_SESSION['user']) && isset($_SESSION['user']['name'])) {
    $userName = $_SESSION['user']['name'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About LET PAY YOU - Leading Financial Technology Platform</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
            line-height: 1.8;
        }

        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            min-height: 100vh;
            box-shadow: 0 0 30px rgba(0,0,0,0.1);
        }

        /* Header */
        .header {
            background: white;
            padding: 30px 40px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo-section img {
            height: 35px;
        }

        .logo-section h1 {
            color: #2d3748;
            font-size: 1.4rem;
            font-weight: 700;
        }

        .back-btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 30px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 1rem;
        }

        .back-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }

        /* Content Area */
        .content {
            padding: 30px 20px;
        }

        /* Hero Section */
        .hero-section {
            text-align: center;
            margin-bottom: 40px;
            background: linear-gradient(135deg, #f8f9ff, #e6edff);
            padding: 40px 30px;
            border-radius: 15px;
            border: 1px solid #e0e7ff;
        }

        .hero-title {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 15px;
            letter-spacing: -1px;
        }

        .hero-subtitle {
            font-size: 1.1rem;
            color: #666;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .hero-description {
            font-size: 0.95rem;
            color: #555;
            max-width: 600px;
            margin: 0 auto;
            line-height: 1.5;
        }

        /* Section Styles */
        .section {
            margin-bottom: 30px;
            padding: 20px 15px;
        }

        .ceo-section {
            background: none;
            border: none;
        }

        .security-section {
            background: none;
            border: none;
        }

        .privacy-section {
            background: none;
            border: none;
        }

        .section-header {
            text-align: center;
            margin-bottom: 25px;
        }

        .section-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 10px;
            letter-spacing: -1px;
        }

        .section-subtitle {
            font-size: 0.95rem;
            color: #666;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .section-content {
            font-size: 0.85rem;
            color: #333;
            line-height: 1.6;
            text-align: left;
            max-width: 100%;
            margin: 0;
        }

        .section-content p {
            margin-bottom: 15px;
            text-indent: 0;
        }

        /* CEO Highlight */
        .ceo-highlight {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            padding: 25px;
            border-radius: 15px;
            margin: 20px 0;
            text-align: center;
            box-shadow: 0 5px 15px rgba(245, 158, 11, 0.3);
        }

        .ceo-name {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .ceo-title {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        /* Professional Images */
        .image-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 25px 0;
        }

        .professional-image {
            width: 100%;
            height: 180px;
            border-radius: 10px;
            object-fit: cover;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }

        .professional-image:hover {
            transform: translateY(-3px);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin: 25px 0;
        }

        .stat-card {
            background: rgba(255,255,255,0.9);
            padding: 20px 15px;
            border-radius: 10px;
            text-align: center;
            border: 1px solid rgba(102, 126, 234, 0.2);
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
        }

        .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 8px;
        }

        .stat-label {
            font-size: 0.8rem;
            color: #666;
            font-weight: 600;
        }

        /* Contact Section */
        .contact-info {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 25px;
            border-radius: 15px;
            margin: 25px 0;
            text-align: center;
        }

        .contact-title {
            font-size: 1.4rem;
            margin-bottom: 15px;
            font-weight: 700;
        }

        .contact-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .contact-item {
            background: rgba(255,255,255,0.1);
            padding: 15px;
            border-radius: 10px;
            backdrop-filter: blur(10px);
        }

        .contact-item i {
            font-size: 1.2rem;
            margin-bottom: 8px;
            color: #ffd700;
        }

        .contact-item h4 {
            font-size: 0.9rem;
            margin-bottom: 5px;
        }

        .contact-item p {
            font-size: 0.8rem;
            opacity: 0.9;
        }

        

        @media (max-width: 768px) {
            .content {
                padding: 20px 15px;
            }
            
            .hero-title {
                font-size: 2.5rem;
            }
            
            .section {
                padding: 20px 15px;
                margin-bottom: 40px;
            }
            
            .section-title {
                font-size: 2rem;
            }
            
            .section-content {
                font-size: 1.1rem;
                line-height: 1.6;
            }
            
            .section-content p {
                margin-bottom: 20px;
            }
            
            .nav-container {
                gap: 20px;
            }
            
            .header {
                padding: 15px 20px;
            }
            
            .hero-section {
                padding: 60px 30px;
            }
            
            .hero-description {
                font-size: 1.1rem;
            }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- Header -->
        <div class="header">
            <div class="logo-section">
                <img src="logo.svg" alt="LET PAY YOU">
                <h1>LET PAY YOU</h1>
            </div>
            <a href="dashboard.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <!-- Content -->
        <div class="content">
            <!-- Hero Section -->
            <div class="hero-section">
                <h1 class="hero-title">About LET PAY YOU</h1>
                <p class="hero-subtitle">Nigeria's Premier Financial Technology Platform</p>
                <p class="hero-description">Empowering Financial Freedom Through Innovation and Technology. Leading the digital revolution in wealth creation and financial empowerment across Nigeria and West Africa.</p>
            </div>

            <!-- Company Introduction Section -->
            <div class="section ceo-section">
                <div class="section-header">
                    <h2 class="section-title">Our Company & Leadership</h2>
                    <p class="section-subtitle">Building the Future of Digital Finance in Nigeria</p>
                </div>

                <div class="image-grid">
                    <img src="https://images.unsplash.com/photo-1560250097-0b93528c311a?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1000&q=80" alt="CEO Odigie Unity Executive Portrait" class="professional-image">
                    <img src="https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1000&q=80" alt="LET PAY YOU Corporate Headquarters" class="professional-image">
                    <img src="https://images.unsplash.com/photo-1521791136064-7986c2920216?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1000&q=80" alt="Executive Team Collaboration" class="professional-image">
                </div>

                <div class="ceo-highlight">
                    <div class="ceo-name">Odigie Unity</div>
                    <div class="ceo-title">Chief Executive Officer & Founder</div>
                </div>

                <div class="section-content">
                    <p>LET PAY YOU stands as Nigeria's premier financial technology platform, revolutionizing how everyday Nigerians access and grow wealth through innovative digital solutions. Founded and led by visionary entrepreneur <strong>Odigie Unity</strong>, our company represents the pinnacle of financial technology innovation in West Africa, combining cutting-edge technology with deep understanding of local financial needs and aspirations.</p>

                    <p>Our journey began with a simple yet powerful vision: to democratize wealth creation and provide every Nigerian with access to legitimate, sustainable income opportunities through technology. Under the strategic leadership of CEO Odigie Unity, LET PAY YOU has grown from a bold concept into a comprehensive financial ecosystem that serves thousands of users across Nigeria, offering multiple streams of income through our sophisticated platform architecture.</p>

                    <p>At LET PAY YOU, we believe that financial freedom should not be a privilege reserved for the wealthy elite, but a fundamental right accessible to every hardworking Nigerian. Our platform combines traditional investment principles with modern technology, creating a unique ecosystem where users can earn daily income through structured task completion, strategic investments, and engaging gaming experiences that reward skill and dedication.</p>

                    <p>The company's foundation rests on three core pillars: innovation, integrity, and inclusivity. We continuously invest in research and development to ensure our platform remains at the forefront of financial technology, while maintaining the highest standards of security and transparency that our users deserve. Our inclusive approach means that whether you're a university student, working professional, entrepreneur, or retiree, LET PAY YOU offers tailored solutions that fit your lifestyle and financial goals.</p>

                    <p>Under Odigie Unity's leadership, our company has established strategic partnerships with leading Nigerian financial institutions, ensuring that our platform operates within regulatory frameworks while providing seamless integration with the country's banking infrastructure. This approach has positioned LET PAY YOU as a trusted bridge between traditional finance and the digital future, offering our users the security of established banking systems with the flexibility and innovation of modern fintech solutions.</p>

                    <p>Our commitment to excellence extends beyond mere profit generation to encompass comprehensive financial education and empowerment initiatives. We believe that sustainable wealth creation requires not just access to opportunities, but also the knowledge and skills to maximize those opportunities effectively. Through our platform, users don't just earn money – they develop financial literacy, investment acumen, and entrepreneurial mindsets that serve them throughout their lives.</p>

                    <p>Today, LET PAY YOU continues to expand its impact across Nigeria, with plans for regional expansion throughout West Africa. Our success is measured not just in financial metrics, but in the thousands of success stories from users who have achieved financial stability, started businesses, completed education, and transformed their lives through our platform. Every day, we witness the powerful transformation that occurs when technology meets opportunity, and ordinary Nigerians are empowered to achieve extraordinary financial results.</p>

                    <p>As we look toward the future, LET PAY YOU remains committed to innovation, growth, and most importantly, to our users' success. Under Odigie Unity's continued leadership, we are building more than just a financial platform – we are creating a movement that will reshape Nigeria's economic landscape and establish new standards for what financial technology can achieve when it truly serves the people.</p>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number">50K+</div>
                        <div class="stat-label">Active Users</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number">₦2.5B+</div>
                        <div class="stat-label">Total Payouts</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number">99.9%</div>
                        <div class="stat-label">Uptime</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number">24/7</div>
                        <div class="stat-label">Support</div>
                    </div>
                </div>
            </div>

            <!-- Security Section -->
            <div class="section security-section">
                <div class="section-header">
                    <h2 class="section-title">Enterprise-Grade Security</h2>
                    <p class="section-subtitle">Protecting Your Financial Future with Advanced Security Protocols</p>
                </div>

                <div class="image-grid">
                    <img src="https://images.unsplash.com/photo-1563013544-824ae1b704d3?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1000&q=80" alt="Cybersecurity Operations Center" class="professional-image">
                    <img src="https://images.unsplash.com/photo-1558494949-ef010cbdcc31?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1000&q=80" alt="Blockchain Security Infrastructure" class="professional-image">
                    <img src="https://images.unsplash.com/photo-1551808525-51a94da548ce?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1000&q=80" alt="Security Verification Systems" class="professional-image">
                </div>

                <div class="section-content">
                    <p>At LET PAY YOU, security is not just a feature – it's the foundation upon which our entire platform is built. We understand that in the digital financial landscape, trust is earned through demonstrated commitment to protecting our users' assets, personal information, and financial transactions. Our comprehensive security infrastructure employs multiple layers of protection, utilizing the most advanced security technologies available in the financial technology sector.</p>

                    <p>Our platform implements bank-grade encryption protocols that safeguard every transaction, ensuring that your financial data remains completely secure from unauthorized access. We utilize 256-bit SSL encryption for all data transmissions, creating an impenetrable barrier between your sensitive information and potential security threats. This level of encryption is the same standard used by major international banks and financial institutions worldwide, providing our users with absolute confidence in their data security.</p>

                    <p>LET PAY YOU operates under strict compliance with international financial security standards, including PCI DSS compliance for payment processing and adherence to Central Bank of Nigeria regulations governing digital financial services. Our security framework undergoes regular audits by independent cybersecurity firms, ensuring that we maintain the highest standards of protection against evolving digital threats and vulnerabilities.</p>

                    <p>We implement multi-factor authentication systems that add additional layers of security to user accounts, requiring multiple forms of verification before granting access to sensitive account functions. Our advanced fraud detection algorithms continuously monitor all platform activities, identifying and preventing suspicious activities before they can impact our users. These systems utilize machine learning and artificial intelligence to adapt to new threat patterns, ensuring our security measures evolve alongside potential risks.</p>

                    <p>Data protection at LET PAY YOU extends beyond basic security measures to encompass comprehensive privacy safeguards. We never sell, trade, or share user personal information with third parties for marketing purposes. Your personal data, financial information, and transaction history remain strictly confidential and are accessible only to authorized personnel who require such access to provide essential platform services.</p>

                    <p>Our commitment to security includes regular security training for all team members, ensuring that every person who handles user data understands their responsibility in maintaining the highest security standards. We maintain secure, redundant data centers with 24/7 monitoring, automatic backup systems, and disaster recovery protocols that ensure your information remains safe and accessible even in the unlikely event of technical difficulties.</p>

                    <p>LET PAY YOU employs sophisticated transaction monitoring systems that track all financial activities on our platform, automatically flagging unusual patterns or potentially fraudulent activities for immediate investigation. Our security team works around the clock to monitor platform activities, respond to security alerts, and implement protective measures that keep our users' financial assets safe from any form of unauthorized access or fraudulent activity.</p>

                    <p>We believe that transparency in security practices builds trust, which is why we regularly publish security updates and maintain open communication with our users about the measures we take to protect their interests. Our platform includes built-in security features that allow users to monitor their own account activities, set spending limits, and receive instant notifications about any account access or transaction activities, putting users in control of their own security.</p>
                </div>
            </div>

            <!-- Privacy Section -->
            <div class="section privacy-section">
                <div class="section-header">
                    <h2 class="section-title">Privacy & Data Protection</h2>
                    <p class="section-subtitle">Your Privacy is Our Priority - Comprehensive Data Protection Policies</p>
                </div>

                <div class="image-grid">
                    <img src="https://images.unsplash.com/photo-1555949963-ff9fe0c870eb?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1000&q=80" alt="Data Privacy Protection Center" class="professional-image">
                    <img src="https://images.unsplash.com/photo-1516321318423-f06f85e504b3?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1000&q=80" alt="Compliance and Legal Framework" class="professional-image">
                    <img src="https://images.unsplash.com/photo-1573164713714-d95e436ab8d6?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1000&q=80" alt="User Data Control Systems" class="professional-image">
                </div>

                <div class="section-content">
                    <p>Privacy protection at LET PAY YOU represents one of our most fundamental commitments to our users. We recognize that in today's digital age, personal privacy has become increasingly valuable and vulnerable, which is why we have implemented comprehensive privacy protection policies that go far beyond basic regulatory requirements. Our approach to data protection is built on the principle that your personal information belongs to you, and we serve merely as custodians who must earn and maintain your trust through transparent and responsible data handling practices.</p>

                    <p>LET PAY YOU operates under a strict "privacy by design" philosophy, meaning that privacy protection is integrated into every aspect of our platform development and operations from the ground up. We collect only the minimum personal information necessary to provide our services effectively, and we use this information exclusively for legitimate business purposes related to your account management, transaction processing, and platform security. We never use your personal data for unauthorized marketing activities or share it with third parties for their commercial benefit.</p>

                    <p>Our data retention policies ensure that personal information is stored only for as long as necessary to fulfill the purposes for which it was collected or as required by applicable laws and regulations. When personal data is no longer needed, it is securely destroyed using industry-standard deletion protocols that ensure complete removal from all systems and backup archives. This approach minimizes your privacy exposure and demonstrates our commitment to responsible data stewardship throughout the entire data lifecycle.</p>

                    <p>We provide our users with complete transparency regarding how their personal information is collected, used, and protected through our detailed privacy policy, which is written in clear, understandable language rather than complex legal terminology. Our users have the right to access their personal data, request corrections to inaccurate information, and in certain circumstances, request deletion of their personal information in accordance with applicable privacy laws and regulations.</p>

                    <p>LET PAY YOU implements advanced data anonymization and pseudonymization techniques that allow us to analyze platform usage patterns and improve our services without compromising individual user privacy. When we need to use data for analytical purposes, we remove or obscure personally identifiable information, ensuring that insights can be gained without exposing individual user identities or sensitive personal details.</p>

                    <p>Our platform includes robust user privacy controls that allow individuals to manage their own privacy settings and data sharing preferences. Users can control what information is visible in their profiles, manage communication preferences, and set permissions for various platform features. These controls put privacy management directly in the hands of our users, allowing them to customize their privacy experience according to their personal comfort levels and preferences.</p>

                    <p>We maintain strict internal policies governing employee access to user data, ensuring that only authorized personnel with legitimate business needs can access personal information. All team members receive comprehensive privacy training and are bound by confidentiality agreements that extend beyond their employment with LET PAY YOU. Our access logging systems track all interactions with user data, creating an audit trail that helps ensure accountability and detect any unauthorized access attempts.</p>

                    <p>LET PAY YOU is committed to staying current with evolving privacy regulations and international best practices in data protection. We regularly review and update our privacy practices to ensure compliance with applicable laws while maintaining the highest standards of user privacy protection. Our legal and compliance teams work continuously to monitor regulatory changes and implement necessary updates to our privacy protection framework, ensuring that our users always benefit from the most current and comprehensive privacy protections available.</p>
                </div>
            </div>

            <!-- Contact Information -->
            <div class="contact-info">
                <h2 class="contact-title">Contact Our Leadership Team</h2>
                <p style="font-size: 1.2rem; margin-bottom: 30px;">Get in touch with CEO Odigie Unity and our executive team</p>
                
                <div class="contact-details">
                    <div class="contact-item">
                        <i class="fab fa-whatsapp"></i>
                        <h4>WhatsApp</h4>
                        <p>+61468302435</p>
                    </div>
                    <div class="contact-item">
                        <i class="fas fa-envelope"></i>
                        <h4>Email</h4>
                        <p>Odigieunity81@gmail.com</p>
                    </div>
                    <div class="contact-item">
                        <i class="fas fa-phone"></i>
                        <h4>Phone</h4>
                        <p>+2349051880966</p>
                    </div>
                </div>
            </div>
        </div>

        
    </div>
</body>
</html>