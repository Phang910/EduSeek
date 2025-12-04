(function() {
    'use strict';
    
    // Get DOM elements
    const chatbotToggle = document.getElementById('chatbot-toggle');
    const chatbotWindow = document.getElementById('chatbot-window');
    const chatbotMinimize = document.getElementById('chatbot-minimize');
    const chatbotClose = document.getElementById('chatbot-close');
    const chatbotInput = document.getElementById('chatbot-input');
    const chatbotSend = document.getElementById('chatbot-send');
    const chatbotMessages = document.getElementById('chatbot-messages');
    const chatbotBadge = document.getElementById('chatbot-badge');
    const chatbotWelcomeBubble = document.getElementById('chatbot-welcome-bubble');
    const chatbotWelcomeClose = document.getElementById('chatbot-welcome-close');
    
    // State
    let isOpen = false;
    let isMinimized = false;
    let welcomeBubbleTimer = null;
    
    // Knowledge base - organized by category
    const knowledgeBase = {
        // About EduSeek
        about: {
            keywords: ['what is', 'what is eduseek', 'about eduseek', 'tell me about', 'introduction', 'what does', 'purpose', 'mission'],
            responses: [
                'EduSeek is a platform dedicated to helping parents in Malaysia find the best educational institutions for their children. We provide a comprehensive directory of kindergartens and schools with detailed information to help you make informed decisions.',
                'EduSeek helps parents discover and compare schools across Malaysia. Our platform offers detailed school information, location-based search, reviews, and more to assist you in finding the perfect school for your child.'
            ]
        },
        
        // Search
        search: {
            keywords: ['search', 'find school', 'how to search', 'find schools', 'look for', 'browse', 'explore schools', 'search schools'],
            responses: [
                'You can search for schools by name or location using the search bar in the navigation menu, or visit the School Directory page. You can also filter schools by level (Kindergarten, Primary, Secondary) and use location-based search to find schools near you.',
                'To search for schools, use the search bar at the top of the page, or go to the Directory page. You can search by school name or location, filter by school level, and even find nearby schools using your current location.'
            ]
        },
        
        // Submit/Request School
        submit: {
            keywords: ['submit school', 'add school', 'request school', 'suggest school', 'new school', 'contribute', 'how to submit', 'business request'],
            responses: [
                'To submit a new school (business request), you need to create an account and log in. Then go to "Submit School" in your profile menu or visit the Request School page. Fill in the school details including name, address, contact, level, description, operating hours, budget, and location. After submission, our admin will review and approve it.',
                'You can submit a school by registering an account, then clicking on "Submit School" in the navigation menu. You\'ll need to provide school information like name, address, contact details, school level, operating hours, budget, and location on the map. This is called a "business request" and will be reviewed by admin.'
            ]
        },
        
        // School Levels
        levels: {
            keywords: ['level', 'kindergarten', 'primary', 'secondary', 'preschool', 'nursery', 'what levels', 'school types', 'age'],
            responses: [
                'EduSeek includes schools at three main levels: Kindergarten (preschool/nursery), Primary School, and Secondary School. You can filter schools by level when searching to find schools that match your child\'s age group.',
                'We have schools for Kindergarten (ages 3-6), Primary School (ages 7-12), and Secondary School (ages 13-17). Use the level filter on the search page to narrow down your results.'
            ]
        },
        
        // Reviews
        reviews: {
            keywords: ['review', 'rating', 'rate', 'stars', 'feedback', 'comment', 'how to review', 'write review', 'review school'],
            responses: [
                'You can write reviews for schools after creating an account and logging in. On any school details page, click "Write a Review" to rate the school on categories like Location, Service, Facilities, Cleanliness, Value for Money, and Education Quality. You can upload up to 10 photos and add comments. Reviews are approved by admin before being published.',
                'To review a school, log in to your account and visit the school\'s details page. Click "Write a Review" and rate the school across 6 categories, add photos (up to 10 photos), and write your feedback. Reviews are approved by admin before being published.'
            ]
        },
        
        // Location/Nearby
        location: {
            keywords: ['location', 'nearby', 'near me', 'close to', 'distance', 'find nearby', 'geolocation', 'current location', 'map'],
            responses: [
                'You can find schools near you by clicking the "Find Nearby Schools" button on the homepage. This uses your current location to show schools in your area. You can also view school locations on Google Maps on each school\'s details page.',
                'To find nearby schools, use the "Find Nearby Schools" feature on the homepage - it will use your location to show schools close to you. Each school also has an interactive Google Maps view showing its exact location.'
            ]
        },
        
        // Account/Login
        account: {
            keywords: ['account', 'login', 'register', 'sign up', 'sign in', 'profile', 'create account', 'user account', 'vendor account'],
            responses: [
                'To create an account, click "Register" in the top navigation menu. You\'ll need to provide your name, email, phone number, and password. You can register as a regular user or as a vendor. Once registered, you can log in to submit schools, write reviews, manage your profile, manage collections, and track your school submissions. Vendors can also manage their business schools and interested clients.',
                'Click "Register" in the navigation menu to create a free account. With an account, you can submit schools, write reviews, manage your profile, manage collections, and access additional features. If you register as a vendor, you can manage your business schools and see clients interested in your schools after admin approval.'
            ]
        },
        
        // Contact
        contact: {
            keywords: ['contact', 'help', 'support', 'email', 'reach', 'get in touch', 'customer service'],
            responses: [
                'For questions, feedback, or support, please visit our Contact Us page. You can fill out the contact form with your name, email, subject, and message, and we\'ll get back to you soon.',
                'You can contact us through the Contact Us page. Fill in the contact form with your details and message, and our team will respond to you as soon as possible.'
            ]
        },
        
        // Directory
        directory: {
            keywords: ['directory', 'list', 'all schools', 'browse', 'school list', 'directory page'],
            responses: [
                'The School Directory page shows all available schools. You can browse, search, filter by school level, sort by rating or alphabetically, and view detailed information for each school.',
                'Visit the Directory page to see all schools in our database. You can search, filter, and sort schools to find exactly what you\'re looking for.'
            ]
        },
        
        // Features
        features: {
            keywords: ['features', 'what can', 'capabilities', 'functionality', 'what offers', 'services'],
            responses: [
                'EduSeek offers: Comprehensive school directory, Location-based search, Detailed school information with photos, maps, and operating hours, User reviews and ratings (up to 10 photos per review), School submission by community (business requests), "I\'m interested" feature to express interest in schools, "Suggest an edit" to improve school information, "Own this business" for vendors to claim schools, Vendor accounts to manage business schools, Manage collections to save favorite schools, Notification system for admin messages, Multi-language support (English, Malay, Chinese), and User accounts for personalized experience.',
                'Our features include: Search and filter schools by name, location, and level; View school details with photos, maps, operating hours, and budget information; Read and write reviews (up to 10 photos); Submit new schools (business requests); Express interest in schools ("I\'m interested"); Suggest edits to school information; Own and manage business schools (for vendors); Manage collections of favorite schools; Find nearby schools using location; Receive notifications from admin; And access everything in multiple languages.'
            ]
        },
        
        // Greetings
        greeting: {
            keywords: ['hello', 'hi', 'hey', 'greetings', 'good morning', 'good afternoon', 'good evening'],
            responses: [
                'Hello! I\'m the EduSeek Assistant. How can I help you today? You can ask me about searching schools, submitting schools (business requests), school levels, reviews, location search, expressing interest in schools, suggesting edits, managing collections, vendor accounts, or anything else about EduSeek!',
                'Hi there! I\'m here to help you with anything about EduSeek. Feel free to ask me about finding schools, submitting schools, writing reviews, expressing interest, suggesting edits, managing collections, vendor features, or any other questions you might have!'
            ]
        },
        
        // Help
        help: {
            keywords: ['help', 'how do', 'how to', 'guide', 'instructions', 'tutorial', 'steps'],
            responses: [
                'I can help you with: Searching for schools, Submitting new schools (business requests), Writing reviews, Finding nearby schools, Understanding school levels, Creating an account (user or vendor), Expressing interest in schools, Suggesting edits to school information, Managing collections, Managing business schools (for vendors), Managing interested clients (for vendors), And more! Just ask me anything about EduSeek.',
                'I\'m here to assist you with using EduSeek. You can ask me how to search schools, submit schools, write reviews, create accounts, express interest in schools, suggest edits, manage collections, manage business (for vendors), or anything else. What would you like to know?'
            ]
        },
        
        // I'm Interested
        interest: {
            keywords: ['interested', 'i\'m interested', 'express interest', 'show interest', 'interested in school', 'contact school'],
            responses: [
                'You can express interest in a school by clicking the "I\'m interested" button on the school details page. This will open a form where you can provide your name, contact number, email, child\'s year of birth, and an optional message. The school owner (vendor) will be notified and can contact you directly.',
                'To express interest in a school, visit the school\'s details page and click "I\'m interested". Fill in your contact information and your child\'s year of birth. The school owner will receive your information and can contact you. Note: This feature is only available for schools that are not yet owned by any vendor.'
            ]
        },
        
        // Suggest Edit
        suggestEdit: {
            keywords: ['suggest edit', 'suggest an edit', 'edit school', 'correct information', 'update school', 'wrong information', 'fix school'],
            responses: [
                'You can suggest edits to school information by clicking "Suggest an edit" on the school details page (for schools not owned by vendors). This will open a form where you can select what type of information you want to edit (name, address, contact, operating hours, etc.) and provide the corrected information. Admin will review and approve your suggestions.',
                'To suggest an edit to school information, visit the school\'s details page and click "Suggest an edit". Select the type of information you want to correct (name, address, phone, operating hours, description, etc.) and provide the updated information. Your suggestion will be reviewed by admin and applied if approved.'
            ]
        },
        
        // Own Business
        ownBusiness: {
            keywords: ['own business', 'own this business', 'claim school', 'claim business', 'vendor claim', 'become owner'],
            responses: [
                'If you are a vendor and want to claim ownership of a school, click "Own this business" on the school details page. You\'ll need to provide business information and upload verification documents (utility bill, rental contract, or business license - at least 2 of these 3). Admin will review your claim and approve it if valid.',
                'To claim ownership of a school, you need to be a registered vendor. Click "Own this business" on the school details page, fill in your business information, and upload at least 2 verification documents (utility bill, shop rental contract, or SSM/business license). Admin will review your submission and link the school to your vendor account if approved.'
            ]
        },
        
        // Vendor Features
        vendor: {
            keywords: ['vendor', 'vendor account', 'become vendor', 'vendor status', 'manage business', 'manage interests', 'my business'],
            responses: [
                'Vendors are school owners who can manage their business schools on EduSeek. To become a vendor, register an account and select the vendor option. You\'ll need to provide business information and verification documents. Once approved by admin, you can manage your schools, see clients interested in your schools, and update school information.',
                'Vendors can manage their business schools, view and manage clients who expressed interest in their schools, and update school details. To become a vendor, register an account as a vendor and submit verification documents. After admin approval, you\'ll have access to "Manage Business" and "Manage Interests" features in your profile.'
            ]
        },
        
        // Manage Collections
        collections: {
            keywords: ['collection', 'collections', 'save school', 'favorite', 'favorites', 'bookmark', 'manage collection'],
            responses: [
                'You can save schools to your collection by clicking the heart icon on any school details page. To manage your collections, go to your profile page and scroll to the "My Collections" section, or click "Manage Collection" in your profile dropdown menu. You can view all your saved schools there.',
                'To save a school to your collection, click the heart icon on the school details page. You can manage your saved schools by going to your profile page and clicking on "Manage Collection" in the profile dropdown menu or in the Quick Actions section. This will show all schools you\'ve saved.'
            ]
        },
        
        // Budget
        budget: {
            keywords: ['budget', 'fee', 'price', 'cost', 'tuition', 'free', 'sponsored', 'dollar sign'],
            responses: [
                'Schools on EduSeek display their budget information, which can be "Free/Sponsored" or a dollar sign amount (1-5 dollar signs). You can see the budget information on each school\'s details page. This helps you understand the cost range of the school.',
                'Budget information shows whether a school is free/sponsored or has a fee structure represented by dollar signs (1-5). You can find this information on each school\'s details page to help you understand the school\'s pricing.'
            ]
        },
        
        // Operating Hours
        operatingHours: {
            keywords: ['operating hours', 'hours', 'open hours', 'business hours', 'when open', 'opening time', 'closing time'],
            responses: [
                'Each school displays its operating hours on the school details page. Operating hours can show specific days and times, "Open 24 hours", or "Closed" status. You can view this information to know when the school is available.',
                'School operating hours are displayed on each school\'s details page. This information shows which days the school is open, the opening and closing times, or if it\'s open 24 hours or closed. This helps you plan your visit or contact.'
            ]
        },
        
        // Notifications
        notifications: {
            keywords: ['notification', 'notifications', 'messages', 'admin message', 'bell icon', 'unread'],
            responses: [
                'EduSeek has a notification system where you can receive messages from admin. You\'ll see a bell icon in the navigation bar with a red dot if you have unread notifications. Click the bell to view your messages. Notifications can include review rejections, edit suggestion rejections, business request rejections, or general messages from admin.',
                'You can receive notifications from admin through the bell icon in the navigation bar. If you have unread messages, you\'ll see a red dot next to the bell. Click it to view your notifications. These can include updates about your reviews, edit suggestions, business requests, or other important messages.'
            ]
        }
    };
    
    // Get translation function (assumes it's available globally)
    function getTranslation(key, defaultValue = '') {
        if (typeof window.translations !== 'undefined' && window.translations[key]) {
            return window.translations[key];
        }
        // Fallback: try to get from data attributes if available
        const element = document.querySelector(`[data-translate="${key}"]`);
        if (element) {
            return element.textContent || element.innerHTML;
        }
        return defaultValue;
    }
    
    // Initialize chatbot
    function init() {
        if (!chatbotToggle || !chatbotWindow) return;
        
        // Event listeners
        chatbotToggle.addEventListener('click', toggleChatbot);
        chatbotMinimize.addEventListener('click', minimizeChatbot);
        chatbotClose.addEventListener('click', closeChatbot);
        chatbotSend.addEventListener('click', sendMessage);
        chatbotInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                sendMessage();
            }
        });
        
        // Welcome bubble close button
        if (chatbotWelcomeClose) {
            chatbotWelcomeClose.addEventListener('click', hideWelcomeBubble);
        }
        
        // Show welcome bubble only on first visit or after login
        showWelcomeBubbleIfNeeded();
        
        // Close on outside click (optional - can be removed if not needed)
        document.addEventListener('click', function(e) {
            if (isOpen && !chatbotWindow.contains(e.target) && !chatbotToggle.contains(e.target)) {
                // Don't close on outside click - let user explicitly close
            }
        });
    }
    
    // Show welcome bubble only on first visit or after login
    function showWelcomeBubbleIfNeeded() {
        if (!chatbotWelcomeBubble) return;
        
        // Check if user just logged in (check for session flag in URL or page load)
        const urlParams = new URLSearchParams(window.location.search);
        const justLoggedIn = urlParams.get('login') === '1' || 
                             (document.body && document.body.dataset.justLoggedIn === 'true');
        
        // Check if it's first visit using localStorage
        const hasVisitedBefore = localStorage.getItem('eduseek_chatbot_welcome_seen') === 'true';
        
        // Show bubble only if:
        // 1. User just logged in, OR
        // 2. It's the first visit
        if (justLoggedIn || !hasVisitedBefore) {
            // Mark as visited in localStorage (but only if not logged in)
            if (!hasVisitedBefore) {
                localStorage.setItem('eduseek_chatbot_welcome_seen', 'true');
            }
            
            // Show the bubble after a short delay (500ms) for better UX
            setTimeout(() => {
                chatbotWelcomeBubble.style.display = 'block';
                chatbotWelcomeBubble.classList.remove('fade-out');
                
                // Auto-dismiss after 5 seconds
                welcomeBubbleTimer = setTimeout(() => {
                    hideWelcomeBubble();
                }, 5000);
            }, 500);
        }
    }
    
    // Hide welcome bubble
    function hideWelcomeBubble() {
        if (!chatbotWelcomeBubble) return;
        
        // Clear timer if exists
        if (welcomeBubbleTimer) {
            clearTimeout(welcomeBubbleTimer);
            welcomeBubbleTimer = null;
        }
        
        // Add fade-out class
        chatbotWelcomeBubble.classList.add('fade-out');
        
        // Remove from DOM after animation
        setTimeout(() => {
            chatbotWelcomeBubble.style.display = 'none';
            chatbotWelcomeBubble.classList.remove('fade-out');
        }, 500);
    }
    
    // Toggle chatbot
    function toggleChatbot() {
        if (isOpen) {
            closeChatbot();
        } else {
            openChatbot();
        }
    }
    
    // Open chatbot
    function openChatbot() {
        isOpen = true;
        isMinimized = false;
        chatbotWindow.classList.add('active');
        chatbotWindow.classList.remove('minimized');
        chatbotInput.focus();
        
        // Hide badge if visible
        if (chatbotBadge) {
            chatbotBadge.style.display = 'none';
        }
        
        // Scroll to bottom
        scrollToBottom();
    }
    
    // Close chatbot
    function closeChatbot() {
        isOpen = false;
        isMinimized = false;
        chatbotWindow.classList.remove('active');
        chatbotWindow.classList.remove('minimized');
        chatbotInput.value = '';
    }
    
    // Minimize chatbot
    function minimizeChatbot() {
        isMinimized = !isMinimized;
        if (isMinimized) {
            chatbotWindow.classList.add('minimized');
        } else {
            chatbotWindow.classList.remove('minimized');
            chatbotInput.focus();
        }
    }
    
    // Send message
    function sendMessage() {
        const message = chatbotInput.value.trim();
        if (!message) return;
        
        // Add user message
        addMessage(message, 'user');
        
        // Clear input
        chatbotInput.value = '';
        chatbotSend.disabled = true;
        
        // Show typing indicator
        showTypingIndicator();
        
        // Process message and get response (with delay to simulate thinking)
        setTimeout(() => {
            hideTypingIndicator();
            const response = getResponse(message);
            addMessage(response, 'bot');
            chatbotSend.disabled = false;
            chatbotInput.focus();
        }, 800 + Math.random() * 400); // Random delay between 800-1200ms
    }
    
    // Get response from knowledge base
    function getResponse(message) {
        const lowerMessage = message.toLowerCase();
        
        // Check each category in knowledge base
        for (const category in knowledgeBase) {
            const categoryData = knowledgeBase[category];
            const matches = categoryData.keywords.some(keyword => 
                lowerMessage.includes(keyword.toLowerCase())
            );
            
            if (matches) {
                // Return random response from this category
                const responses = categoryData.responses;
                return responses[Math.floor(Math.random() * responses.length)];
            }
        }
        
        // Fallback response
        const fallbackText = getTranslation('chatbot_fallback', 
            'I\'m not sure how to help with that. Please visit our <a href="contact.php">Contact Us</a> page for more information, or try asking about: searching schools, submitting schools (business requests), school levels, reviews, location search, expressing interest in schools, suggesting edits, managing collections, vendor accounts, managing business schools, or notifications.'
        );
        
        return fallbackText;
    }
    
    // Add message to chat
    function addMessage(content, type) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `chatbot-message chatbot-${type}-message`;
        
        const avatarDiv = document.createElement('div');
        avatarDiv.className = 'chatbot-message-avatar';
        const avatarIcon = type === 'bot' ? 'fa-robot' : 'fa-user';
        avatarDiv.innerHTML = `<i class="fas ${avatarIcon}"></i>`;
        
        const contentDiv = document.createElement('div');
        contentDiv.className = 'chatbot-message-content';
        contentDiv.innerHTML = content; // Allow HTML for links
        
        messageDiv.appendChild(avatarDiv);
        messageDiv.appendChild(contentDiv);
        
        chatbotMessages.appendChild(messageDiv);
        
        // Scroll to bottom
        scrollToBottom();
    }
    
    // Show typing indicator
    function showTypingIndicator() {
        const typingDiv = document.createElement('div');
        typingDiv.className = 'chatbot-message chatbot-bot-message';
        typingDiv.id = 'chatbot-typing-indicator';
        
        const avatarDiv = document.createElement('div');
        avatarDiv.className = 'chatbot-message-avatar';
        avatarDiv.innerHTML = '<i class="fas fa-robot"></i>';
        
        const typingContentDiv = document.createElement('div');
        typingContentDiv.className = 'chatbot-typing-indicator';
        typingContentDiv.innerHTML = '<span></span><span></span><span></span>';
        
        typingDiv.appendChild(avatarDiv);
        typingDiv.appendChild(typingContentDiv);
        
        chatbotMessages.appendChild(typingDiv);
        scrollToBottom();
    }
    
    // Hide typing indicator
    function hideTypingIndicator() {
        const indicator = document.getElementById('chatbot-typing-indicator');
        if (indicator) {
            indicator.remove();
        }
    }
    
    // Scroll to bottom of messages
    function scrollToBottom() {
        setTimeout(() => {
            chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
        }, 100);
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
})();

