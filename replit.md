# Replit Project Guide

## Overview
This is a PHP investment/gaming platform called "LET PAY YOU" that allows users to invest in various tiers (Bronze, Silver, Gold, Platinum, Diamond) and participate in gaming activities to earn returns. The system manages user investments, tracks daily progress, handles commission-based earnings, and provides multiple gaming options including crash, aviator, dice, mines, and more.

## User Preferences
Preferred communication style: Simple, everyday language.

## Recent Changes
**September 2025**: Successfully migrated from local JSON file storage to Firebase Realtime Database integration. All investment tier data, user profiles, and gaming statistics now load from cloud storage instead of local files.

## System Architecture
**Data Storage Pattern**: Firebase Realtime Database integration replaces previous local JSON storage. The system uses Firebase REST API for all data persistence operations through a unified connector system.

**Firebase Integration**: Uses Firebase Realtime Database at `https://wepayyou-c100d-default-rtdb.firebaseio.com/` with structured data paths for:
- User profiles and balances (`/users/`)
- Investment tier configurations (`/bronze/`, `/silver/`, `/gold/`, etc.)
- User-specific data (`/users_data/{email}/transactions/`, `/users_data/{email}/notifications/`)
- Gaming statistics and progress tracking

**Investment System**: Multi-tier investment structure with daily order completion requirements:
- Bronze: Lower investment, basic returns
- Silver: Medium investment, moderate returns  
- Gold: Higher investment, better returns
- Platinum: Premium investment, high returns
- Diamond: Elite investment, maximum returns

**Gaming Platform**: Integrated gaming features including crash rockets, aviator, dice games, mines, plinko, spin wheel, step climber, and treasure hunt with commission-based earnings.

**Security Features**: XSS protection with htmlspecialchars() escaping, secure Firebase authentication, and protected routing system.

## Technical Stack
- **Backend**: PHP with Firebase Realtime Database
- **Frontend**: HTML/CSS/JavaScript with responsive design
- **Database**: Firebase Realtime Database (cloud-based)
- **Security**: Input sanitization, XSS protection, secure Firebase connector
- **Hosting**: Replit environment with PHP server on port 5000

## Project Architecture
**Main Files**:
- `dashboard.php` - User dashboard with investment tracking
- `invest.php` - Investment package selection and management
- `firebase_connector.php` - Firebase database integration
- `auth.php` - User authentication and session management
- Gaming files: `crash.php`, `aviator.php`, `dice.php`, `mines.php`, etc.

**Known Issues**: Minor error in `crash.php` line 156 with array access (non-critical, other pages working correctly)

The system successfully loads all data from Firebase cloud database instead of local storage, providing better scalability and reliability.