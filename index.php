<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Ride_Sharing - Home</title>
  <link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"
  />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" />
  <style>
    /* Reset & base */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    
    body {
      font-family: 'Poppins', Arial, sans-serif;
      background: linear-gradient(135deg, #667eea 0%, rgb(168, 149, 187) 100%);
      color: #fff;
      line-height: 1.6;
      min-height: 100vh;
      position: relative;
      overflow-x: hidden;
    }

    /* Animated background */
    body::before {
      content: '';
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: url('https://thumbs.dreamstime.com/b/vector-illustration-autonomous-online-car-sharing-service-controlled-via-smartphone-app-modern-phone-location-mark-215947180.jpg') no-repeat center center;
      background-size: cover;
      opacity: 0.1;
      z-index: -2;
      animation: slowZoom 20s ease-in-out infinite alternate;
    }

    body::after {
      content: '';
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: linear-gradient(45deg, rgba(102, 126, 234, 0.8), rgba(118, 75, 162, 0.8));
      z-index: -1;
    }

    @keyframes slowZoom {
      0% { transform: scale(1); }
      100% { transform: scale(1.05); }
    }

    /* Floating particles animation */
    .particles {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      pointer-events: none;
      z-index: 1;
    }

    .particle {
      position: absolute;
      width: 4px;
      height: 4px;
      background: rgba(255, 255, 255, 0.6);
      border-radius: 50%;
      animation: float 6s ease-in-out infinite;
    }

    .particle:nth-child(1) { left: 10%; animation-delay: 0s; }
    .particle:nth-child(2) { left: 20%; animation-delay: 1s; }
    .particle:nth-child(3) { left: 30%; animation-delay: 2s; }
    .particle:nth-child(4) { left: 40%; animation-delay: 3s; }
    .particle:nth-child(5) { left: 50%; animation-delay: 4s; }
    .particle:nth-child(6) { left: 60%; animation-delay: 0.5s; }
    .particle:nth-child(7) { left: 70%; animation-delay: 1.5s; }
    .particle:nth-child(8) { left: 80%; animation-delay: 2.5s; }

    @keyframes float {
      0%, 100% { transform: translateY(100vh) scale(0); }
      10% { transform: translateY(90vh) scale(1); }
      90% { transform: translateY(-10vh) scale(1); }
      100% { transform: translateY(-10vh) scale(0); }
    }

    /* Header with glassmorphism */
    .header {
      background: rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(20px);
      border-bottom: 1px solid rgba(255, 255, 255, 0.2);
      color: white;
      padding: 1rem 2rem;
      position: fixed;
      width: 100%;
      top: 0;
      z-index: 1000;
      box-shadow: 0 8px 32px rgba(0,0,0,0.1);
      display: flex;
      justify-content: space-between;
      align-items: center;
      transition: all 0.3s ease;
    }

    .header:hover {
      background: rgba(255, 255, 255, 0.15);
    }

    .logo {
      font-size: 2rem;
      font-weight: 800;
      cursor: pointer;
      user-select: none;
      color: white;
      text-decoration: none;
      background: linear-gradient(45deg, #fff, #ffdd57);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      animation: logoGlow 3s ease-in-out infinite alternate;
    }

    @keyframes logoGlow {
      0% { filter: drop-shadow(0 0 5px rgba(255, 221, 87, 0.5)); }
      100% { filter: drop-shadow(0 0 20px rgba(255, 221, 87, 0.8)); }
    }

    nav ul {
      list-style: none;
      display: flex;
      gap: 2rem;
      align-items: center;
    }

    nav a {
      color: white;
      text-decoration: none;
      font-weight: 600;
      font-size: 1rem;
      transition: all 0.3s ease;
      cursor: pointer;
      padding: 0.5rem 1rem;
      border-radius: 25px;
      position: relative;
      overflow: hidden;
    }

    nav a::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
      transition: left 0.5s;
    }

    nav a:hover::before {
      left: 100%;
    }

    nav a.active,
    nav a:hover,
    nav a:focus {
      color: #ffdd57;
      background: rgba(255, 255, 255, 0.1);
      outline: none;
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    }

    .btn-account {
      background: linear-gradient(45deg, #667eea, rgb(168, 149, 187));
      color: white;
      border: none;
      border-radius: 25px;
      padding: 0.7rem 1.5rem;
      font-weight: 600;
      font-size: 1rem;
      cursor: pointer;
      transition: all 0.3s ease;
      box-shadow: 0 4px 15px rgba(0,0,0,0.2);
      position: relative;
      overflow: hidden;
    }

    .btn-account::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
      transition: left 0.5s;
    }

    .btn-account:hover::before {
      left: 100%;
    }

    .btn-account:hover,
    .btn-account:focus {
      transform: translateY(-3px);
      box-shadow: 0 8px 25px rgba(0,0,0,0.3);
      background: linear-gradient(45deg,rgb(168, 149, 187), #667eea);
      outline: none;
    }

    /* Main content */
    main {
      padding-top: 6rem;
      max-width: 1200px;
      margin: 0 auto;
      min-height: calc(100vh - 6rem);
      position: relative;
      z-index: 10;
      padding-left: 1rem;
      padding-right: 1rem;
    }

    /* Section styles with advanced animations - FIXED OVERLAPPING */
    section {
      margin-bottom: 2rem;
      opacity: 0;
      visibility: hidden;
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      transform: translateY(50px) scale(0.9);
      transition: all 0.8s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }

    section.active {
      opacity: 1;
      visibility: visible;
      position: relative;
      transform: translateY(0) scale(1);
    }

    /* Enhanced glassmorphism cards */
    .ride-section, .drive-section, .business-section, .eats-section, .about-section {
      margin-top: 2rem;
      background: rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(20px);
      padding: 3rem;
      border-radius: 25px;
      box-shadow: 0 8px 32px rgba(0,0,0,0.1);
      border: 1px solid rgba(255, 255, 255, 0.2);
      overflow: hidden;
      transition: all 0.3s ease;
    }

    .ride-section::before, .drive-section::before, .business-section::before, .eats-section::before, .about-section::before {
      content: '';
      position: absolute;
      top: -2px;
      left: -2px;
      right: -2px;
      bottom: -2px;
      background: linear-gradient(45deg, #667eea, rgb(168, 149, 187), #667eea);
      border-radius: 25px;
      z-index: -1;
      animation: borderGlow 3s linear infinite;
    }

    @keyframes borderGlow {
      0% { background-position: 0 0; }
      50% { background-position: 400% 0; }
      100% { background-position: 0 0; }
    }

    .ride-section:hover, .drive-section:hover, .business-section:hover, .eats-section:hover, .about-section:hover {
      transform: translateY(-10px);
      box-shadow: 0 20px 40px rgba(0,0,0,0.2);
    }

    /* Enhanced ride form */
    .ride-form {
      padding: 2.5rem;
      border-radius: 20px;
      background: rgba(255, 255, 255, 0.15);
      backdrop-filter: blur(15px);
      box-shadow: inset 0 0 20px rgba(255, 255, 255, 0.1);
      max-width: 600px;
      margin: 0 auto;
      border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .form-title {
      font-size: 2.2rem;
      margin-bottom: 2rem;
      font-weight: 700;
      color: #fff;
      text-align: center;
      background: linear-gradient(45deg, #fff, #ffdd57);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      animation: titlePulse 2s ease-in-out infinite alternate;
    }

    @keyframes titlePulse {
      0% { transform: scale(1); }
      100% { transform: scale(1.02); }
    }

    .input-group {
      position: relative;
      margin-bottom: 1.8rem;
    }

    .input-icon {
      position: absolute;
      top: 50%;
      left: 20px;
      transform: translateY(-50%);
      color: #ffdd57;
      font-size: 1.3rem;
      pointer-events: none;
      z-index: 2;
      animation: iconFloat 3s ease-in-out infinite;
    }

    @keyframes iconFloat {
      0%, 100% { transform: translateY(-50%); }
      50% { transform: translateY(-60%); }
    }

    .form-input {
      width: 100%;
      padding: 1rem 1.2rem 1rem 3.5rem;
      border: 2px solid rgba(255, 255, 255, 0.3);
      border-radius: 15px;
      font-size: 1.1rem;
      transition: all 0.3s ease;
      background: rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(10px);
      color: white;
      font-weight: 500;
    }

    .form-input::placeholder {
      color: rgba(255, 255, 255, 0.7);
    }

    .form-input:focus {
      border-color: #ffdd57;
      outline: none;
      background: rgba(255, 255, 255, 0.2);
      box-shadow: 0 0 20px rgba(255, 221, 87, 0.3);
      transform: translateY(-2px);
    }

    .time-group {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1.5rem;
    }

    .btn-prices {
      margin-top: 2rem;
      width: 100%;
      background: linear-gradient(45deg, #667eea, rgb(168, 149, 187));
      color: white;
      padding: 1.2rem 0;
      font-size: 1.3rem;
      font-weight: 700;
      border: none;
      border-radius: 15px;
      cursor: pointer;
      transition: all 0.3s ease;
      box-shadow: 0 8px 25px rgba(0,0,0,0.2);
      position: relative;
      overflow: hidden;
    }

    .btn-prices::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
      transition: left 0.5s;
    }

    .btn-prices:hover::before {
      left: 100%;
    }

    .btn-prices:hover,
    .btn-prices:focus {
      background: linear-gradient(45deg, rgb(168, 149, 187), #667eea);
      transform: translateY(-5px);
      box-shadow: 0 15px 35px rgba(0,0,0,0.3);
      outline: none;
    }

    /* Placeholder sections with enhanced styling */
    .placeholder-text {
      font-size: 1.4rem;
      color: rgba(255, 255, 255, 0.9);
      max-width: 600px;
      margin: 2rem auto;
      text-align: center;
      font-weight: 400;
      line-height: 1.8;
    }

    .section-title {
      font-size: 3rem;
      font-weight: 800;
      margin-bottom: 2rem;
      text-align: center;
      background: linear-gradient(45deg, #fff, #ffdd57);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    /* FIXED MODAL STYLES */
    .modal-overlay {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.8);
      backdrop-filter: blur(10px);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 10000;
      opacity: 0;
      visibility: hidden;
      transition: all 0.3s ease;
    }

    .modal-overlay.show {
      opacity: 1;
      visibility: visible;
    }

    .modal-content {
      background: linear-gradient(135deg, #667eea 0%, rgb(168, 149, 187) 100%);
      padding: 2.5rem;
      border-radius: 20px;
      max-width: 500px;
      width: 90%;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
      border: 1px solid rgba(255, 255, 255, 0.2);
      backdrop-filter: blur(20px);
      transform: scale(0.7);
      transition: transform 0.3s ease;
      color: white;
    }

    .modal-overlay.show .modal-content {
      transform: scale(1);
    }

    .modal-title {
      font-size: 1.8rem;
      font-weight: 700;
      margin-bottom: 1.5rem;
      color: #ffdd57;
      text-align: center;
    }

    .modal-info {
      margin-bottom: 1.5rem;
      font-size: 1rem;
      text-align: center;
      color: rgba(255, 255, 255, 0.9);
    }

    .price-list {
      background: rgba(255, 255, 255, 0.1);
      border-radius: 15px;
      overflow: hidden;
      margin-bottom: 2rem;
      backdrop-filter: blur(10px);
    }

    .price-item {
      padding: 1.2rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      border-bottom: 1px solid rgba(255, 255, 255, 0.1);
      transition: background 0.3s ease;
    }

    .price-item:last-child {
      border-bottom: none;
    }

    .price-item:hover {
      background: rgba(255, 255, 255, 0.1);
    }

    .price-type {
      font-weight: 600;
      font-size: 1.1rem;
    }

    .price-range {
      font-weight: 700;
      color: #ffdd57;
      font-size: 1.1rem;
    }

    .modal-buttons {
      display: flex;
      gap: 1rem;
    }

    .modal-btn {
      flex: 1;
      padding: 1rem;
      border: none;
      border-radius: 12px;
      font-weight: 600;
      font-size: 1rem;
      cursor: pointer;
      transition: all 0.3s ease;
    }

    .modal-btn-close {
      background: rgba(255, 255, 255, 0.2);
      color: white;
      border: 1px solid rgba(255, 255, 255, 0.3);
    }

    .modal-btn-close:hover {
      background: rgba(255, 255, 255, 0.3);
      transform: translateY(-2px);
    }

    .modal-btn-book {
      background: linear-gradient(45deg, #ffdd57, #ffc107);
      color: #333;
      font-weight: 700;
    }

    .modal-btn-book:hover {
      background: linear-gradient(45deg, #ffc107, #ffdd57);
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(255, 221, 87, 0.4);
    }

    /* Enhanced footer */
    .footer {
      background: rgba(0, 0, 0, 0.3);
      backdrop-filter: blur(20px);
      color: white;
      padding: 3rem 0 1rem;
      margin-top: 4rem;
      border-top: 1px solid rgba(255, 255, 255, 0.1);
    }

    .container {
      max-width: 960px;
      margin: 0 auto;
      padding: 0 16px;
    }

    .footer-content {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 2rem;
      margin-bottom: 2rem;
    }

    .footer-section h3 {
      margin-bottom: 1rem;
      font-size: 1.2rem;
      color: #ffdd57;
      font-weight: 600;
    }

    .footer-section ul {
      list-style: none;
      padding-left: 0;
    }

    .footer-section li {
      margin-bottom: 0.7rem;
    }

    .footer-section a {
      color: rgba(255, 255, 255, 0.8);
      text-decoration: none;
      transition: all 0.3s;
      font-weight: 400;
    }

    .footer-section a:hover {
      color: #ffdd57;
      transform: translateX(5px);
    }

    .footer-bottom {
      border-top: 1px solid rgba(255, 255, 255, 0.2);
      padding-top: 1rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 1rem;
    }

    .social-links {
      display: flex;
      gap: 1rem;
    }

    .social-links a {
      color: rgba(255, 255, 255, 0.8);
      font-size: 1.5rem;
      transition: all 0.3s;
      padding: 0.5rem;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.1);
    }

    .social-links a:hover {
      color: #ffdd57;
      background: rgba(255, 221, 87, 0.2);
      transform: translateY(-3px) scale(1.1);
    }

    .app-links {
      display: flex;
      gap: 1rem;
    }

    .app-links div {
      background: rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(10px);
      padding: 12px;
      border-radius: 10px;
      color: white;
      font-size: 12px;
      text-align: center;
      width: 120px;
      transition: all 0.3s;
      border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .app-links div:hover {
      background: rgba(255, 255, 255, 0.2);
      transform: translateY(-3px);
    }

    .app-links i {
      font-size: 24px;
      margin-bottom: 4px;
      color: #ffdd57;
    }

    /* Responsive design */
    @media (max-width: 768px) {
      .header {
        padding: 1rem;
        flex-wrap: wrap;
      }
      
      nav ul {
        flex-wrap: wrap;
        gap: 1rem;
        justify-content: center;
        width: 100%;
        margin-top: 1rem;
      }
      
      main {
        padding: 10rem 1rem 4rem;
      }
      
      .ride-section, .drive-section, .business-section, .eats-section, .about-section {
        padding: 2rem;
        margin-top: 2rem;
      }
      
      .form-title, .section-title {
        font-size: 1.8rem;
      }
      
      .time-group {
        grid-template-columns: 1fr;
        gap: 1rem;
      }
      
      .footer-bottom {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
      }
      
      .social-links, .app-links {
        justify-content: center;
      }

      .modal-content {
        padding: 2rem;
        margin: 1rem;
      }

      .modal-buttons {
        flex-direction: column;
      }
    }

    /* Additional micro-animations */
    .input-group:hover .input-icon {
      color: #fff;
      transform: translateY(-50%) scale(1.1);
    }

    .footer-section {
      transition: transform 0.3s ease;
    }

    .footer-section:hover {
      transform: translateY(-5px);
    }

    /* Custom scrollbar */
    ::-webkit-scrollbar {
      width: 8px;
    }

    ::-webkit-scrollbar-track {
      background: rgba(255, 255, 255, 0.1);
    }

    ::-webkit-scrollbar-thumb {
      background: linear-gradient(45deg, #667eea, #764ba2);
      border-radius: 4px;
    }

    ::-webkit-scrollbar-thumb:hover {
      background: linear-gradient(45deg, #764ba2, #667eea);
    }
  </style>
</head>
<body>
  <!-- Floating particles -->
  <div class="particles">
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
    <div class="particle"></div>
  </div>

  <!-- Header -->
  <header class="header" role="banner">
    <div class="nav-container">
      <a href="#" class="logo" aria-label="Ride_Sharing Logo" onclick="showSection('ride')">Ride_Sharing</a>
      <nav role="navigation" aria-label="Primary Navigation">
        <ul class="nav-links">
          <li><a href="#" class="active" data-target="ride" onclick="showSection('ride')">Ride</a></li>
          <li><a href="#" data-target="drive" onclick="showSection('drive')">Drive</a></li>
          <li><a href="#" data-target="business" onclick="showSection('business')">Business</a></li>
          <li><a href="#" data-target="eats" onclick="showSection('eats')">Ride_Sharing Eats</a></li>
          <li><a href="#" data-target="about" onclick="showSection('about')">About</a></li>
          <li>
            <button
              class="btn-account"
              aria-label="My account"
              onclick="window.location.href='login.php'"
              type="button"
            >
              My account
            </button>
          </li>
        </ul>
      </nav>
    </div>
  </header>

  <!-- Main Content -->
  <main class="main-content" role="main">
    <!-- Ride Section -->
    <section class="ride-section active" id="ride" tabindex="-1" aria-label="Request a ride">
      <div class="ride-form" role="form" aria-labelledby="requestRideTitle">
        <h2 class="form-title" id="requestRideTitle">Request a ride</h2>

        <div class="input-group">
          <i class="fas fa-circle input-icon" aria-hidden="true"></i>
          <input
            type="text"
            class="form-input"
            placeholder="Pickup location"
            id="pickup"
            aria-label="Pickup location"
            autocomplete="off"
            required
          />
        </div>

        <div class="input-group">
          <i class="fas fa-square input-icon" aria-hidden="true"></i>
          <input
            type="text"
            class="form-input"
            placeholder="Dropoff location"
            id="dropoff"
            aria-label="Dropoff location"
            autocomplete="off"
            required
          />
        </div>

        <div class="time-group">
          <div class="input-group">
            <i class="fas fa-calendar input-icon" aria-hidden="true"></i>
            <input
              type="text"
              class="form-input"
              value="Today"
              readonly
              aria-label="Ride date"
            />
          </div>
          <div class="input-group">
            <i class="fas fa-clock input-icon" aria-hidden="true"></i>
            <select class="form-input" aria-label="Ride time">
              <option>Now</option>
              <option>Schedule for later</option>
            </select>
          </div>
        </div>

        <button class="btn-prices" type="button" onclick="showPrices()" aria-label="See price estimates">
          See prices
        </button>
      </div>
    </section>

    <!-- Drive Section -->
    <section class="drive-section" id="drive" tabindex="-1" aria-label="Drive with Ride_Sharing">
      <h1 class="section-title">Drive</h1>
      <p class="placeholder-text">Welcome to the Drive page. Manage your driving profile or sign up to become a driver and start earning money on your schedule.</p>
    </section>

    <!-- Business Section -->
    <section class="business-section" id="business" tabindex="-1" aria-label="Business opportunities with Ride_Sharing">
      <h1 class="section-title">Business</h1>
      <p class="placeholder-text">Explore Ride_Sharing business solutions and partnership opportunities. From corporate accounts to fleet management, we have solutions for every business need.</p>
    </section>

    <!-- Ride_Sharing Eats Section -->
    <section class="eats-section" id="eats" tabindex="-1" aria-label="Ride_Sharing Eats food delivery service">
      <h1 class="section-title">Ride_Sharing Eats</h1>
      <p class="placeholder-text">Discover our food delivery services and partner restaurants. Get your favorite meals delivered right to your doorstep with just a few taps.</p>
    </section>

    <!-- About Section -->
    <section class="about-section" id="about" tabindex="-1" aria-label="About Ride_Sharing">
      <h1 class="section-title">About Us</h1>
      <p class="placeholder-text">Learn more about Ride_Sharing, our mission, and our team.</p>
    </section>
  </main>

  <script>
    function showSection(sectionId) {
      // Hide all sections
      const sections = document.querySelectorAll('main > section');
      sections.forEach(sec => {
        sec.classList.remove('active');
        sec.setAttribute('aria-hidden', 'true');
      });

      // Remove active class from nav links
      const navLinks = document.querySelectorAll('.nav-links a');
      navLinks.forEach(link => link.classList.remove('active'));

      // Show selected section
      const selected = document.getElementById(sectionId);
      if(selected) {
        selected.classList.add('active');
        selected.removeAttribute('aria-hidden');
        selected.focus();
      }

      // Highlight nav link
      const activeLink = document.querySelector(`.nav-links a[data-target="${sectionId}"]`);
      if(activeLink) activeLink.classList.add('active');
    }

    // Price modal function (same as before)
    function showPrices() {
      const pickup = document.getElementById("pickup").value.trim();
      const dropoff = document.getElementById("dropoff").value.trim();

      if (!pickup || !dropoff) {
        alert("Please enter both pickup and dropoff locations.");
        return;
      }

      const basePrice = 80;
      const pricePerKm = 15;
      const distance = Math.floor(Math.random() * 25) + 5; // Random 5-30 km
      const Ride_SharingX = basePrice + distance * pricePerKm;
      const Ride_SharingPremium = Math.round(Ride_SharingX * 1.5);
      const Ride_SharingXL = Math.round(Ride_SharingX * 1.8);

      const modal = document.createElement("div");
      modal.style.cssText = `
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9999;
      `;

      modal.innerHTML = `
        <div style="
          background: white;
          padding: 2rem;
          border-radius: 10px;
          max-width: 400px;
          width: 90%;
          box-shadow: 0 8px 25px rgba(28,163,216,0.2);
        ">
          <h3 style="margin-bottom: 1rem; color:#166ba0;">Price Estimate</h3>
          <p style="margin-bottom: 1rem; color:#444;">From <strong>${pickup}</strong> to <strong>${dropoff}</strong></p>
          <p style="margin-bottom: 1rem; color:#444;">Approximate distance: <strong>${distance} km</strong></p>
          <div style="border: 1px solid #eee; border-radius: 8px;">
            <div style="padding: 1rem; display: flex; justify-content: space-between; border-bottom: 1px solid #eee;">
              <span>Ride_SharingX</span>
              <span>৳${Ride_SharingX - 50} - ৳${Ride_SharingX + 50}</span>
            </div>
            <div style="padding: 1rem; display: flex; justify-content: space-between; border-bottom: 1px solid #eee;">
              <span>Ride_Sharing Premium</span>
              <span>৳${Ride_SharingPremium - 50} - ৳${Ride_SharingPremium + 50}</span>
            </div>
            <div style="padding: 1rem; display: flex; justify-content: space-between;">
              <span>Ride_Sharing XL</span>
              <span>৳${Ride_SharingXL - 50} - ৳${Ride_SharingXL + 50}</span>
            </div>
          </div>
          <div style="margin-top: 1.5rem; display: flex; gap: 1rem;">
            <button id="closeModalBtn" style="flex: 1; padding: 0.75rem; background: #eee; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">Close</button>
<a href="login.php" style="flex: 1; text-decoration: none;">
  <button id="bookNowBtn" style="width: 100%; padding: 0.75rem; background: #1ca3d8; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">
    Book Now
  </button>
</a>

          </div>
        </div>
      `;

      document.body.appendChild(modal);

      document.getElementById("closeModalBtn").onclick = () => modal.remove();

      document.getElementById("bookNowBtn").onclick = () => {
        alert("Redirecting to booking confirmation page...");
        modal.remove();
        // window.location.href = "booking.php?pickup=" + encodeURIComponent(pickup) + "&dropoff=" + encodeURIComponent(dropoff);
      };
    }
  </script>

  <footer class="footer">
    <div class="container">
        <div class="footer-content">
            <div class="footer-section">
                <h3>Company</h3>
                <ul>
                    <li><a href="#">About us</a></li>
                    <li><a href="#">Our offerings</a></li>
                    <li><a href="#">Newsroom</a></li>
                    <li><a href="#">Investors</a></li>
                    <li><a href="#">Blog</a></li>
                    <li><a href="#">Careers</a></li>
                    <li><a href="#">Ride_Sharing AI</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h3>Products</h3>
                <ul>
                    <li><a href="#">Ride</a></li>
                    <li><a href="#">Drive</a></li>
                    <li><a href="#">Deliver</a></li>
                    <li><a href="#">Eat</a></li>
                    <li><a href="#">Ride_Sharing for Business</a></li>
                    <li><a href="#">Ride_Sharing Freight</a></li>
                    <li><a href="#">Gift cards</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h3>Global citizenship</h3>
                <ul>
                    <li><a href="#">Safety</a></li>
                    <li><a href="#">Sustainability</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h3>Travel</h3>
                <ul>
                    <li><a href="#">Reserve</a></li>
                    <li><a href="#">Airports</a></li>
                    <li><a href="#">Cities</a></li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <div>
                <div class="social-links">
                    <a href="#"><i class="fab fa-facebook"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-youtube"></i></a>
                    <a href="#"><i class="fab fa-linkedin"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                </div>
            </div>
            <div>
                <span>English</span> | <span>Dhaka</span>
            </div>
            <div class="app-links">
                <div>
                    <i class="fab fa-google-play"></i><br>
                    <span>GET IT ON<br><strong>Google Play</strong></span>
                </div>
                <div>
                    <i class="fab fa-apple"></i><br>
                    <span>Download on the<br><strong>App Store</strong></span>
                </div>
            </div>
        </div>

        <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #1a8db8; text-align: right; color: #c0c0c0; font-size: 0.9rem;">
            Bangladesh ridesharing related information
        </div>
    </div>
</footer>
</body>
</html>
