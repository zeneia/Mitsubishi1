<?php 
$pageTitle = "Service Center - Mitsubishi Motors";
include 'header.php'; 
?>

<style>
  body {
    background-image: url(../includes/images/backbg.jpg); 
    background-size: cover; /* scales image to cover the whole area */
    background-position: center; /* centers the image */
    background-repeat: no-repeat;
    zoom: 80%;
  }
  .container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 32px;
  }
  .page-title {
    color: #ffffffff;
    font-size: 2.5rem;
    font-weight: bold;
    margin-bottom: 24px;
    text-align: center;
  }
  

  .services-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 24px;
    margin: 32px 0;
  }
  .service-card {
    position: relative;
    background: rgba(24, 24, 24, 0.7);
    border-radius: 12px;
    padding: 30px;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 215, 0, 0.2);
    text-align: center;
    transition: all 0.4s ease;
    overflow: hidden;
  }
  .service-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: radial-gradient(circle at top right, rgba(255, 215, 0, 0.1), transparent 70%);
    opacity: 0;
    transition: opacity 0.4s ease;
  }

  .service-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 15px 30px rgba(0, 0, 0, 0.3);
    border-color: rgba(255, 215, 0, 0.5);
  }

  .service-card:hover::before {
    opacity: 1;
  }

  .service-icon {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, #ffd700, #ffa500);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    font-size: 2.2rem;
    color: #111;
    position: relative;
    z-index: 1;
    box-shadow: 0 8px 20px rgba(255, 215, 0, 0.3);
    transition: transform 0.4s ease;
  }

  .service-card:hover .service-icon {
    transform: rotateY(360deg);
  }

  .service-title {
    font-size: 1.6rem;
    font-weight: 700;
    background: linear-gradient(to right, #ffd700, #ffec8b);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-bottom: 15px;
    position: relative;
    z-index: 1;
  }

  .service-description {
    color: #ddd;
    margin-bottom: 25px;
    line-height: 1.6;
    position: relative;
    z-index: 1;
  }

  .service-btn {
    background: linear-gradient(45deg, #ffd700, #ffec8b, #ffd700);
    background-size: 200% 200%;
    color: #b80000;
    border: none;
    padding: 12px 24px;
    border-radius: 6px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.5s ease;
    position: relative;
    z-index: 1;
    overflow: hidden;
  }
  
  .service-btn:hover {
    background-position: right center;
    color: #000;
    box-shadow: 0 5px 15px rgba(255, 215, 0, 0.4);
  }

  .service-btn::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 150%;
    height: 200%;
    background: radial-gradient(circle, rgba(255, 215, 0, 0.4), transparent 60%);
    transform: translate(-50%, -50%) scale(0);
    opacity: 0;
    transition: transform 0.5s ease, opacity 0.5s ease;
    z-index: -1;
  }

  .service-btn:hover::after {
    transform: translate(-50%, -50%) scale(1);
    opacity: 1;
  }

  /* Appointment Form Styling */
  .appointment-section {
    position: relative;
    background: linear-gradient(135deg, rgba(24, 24, 24, 0.8), rgba(139, 0, 0, 0.3));
    border-radius: 12px;
    padding: 40px;
    margin-top: 50px;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 215, 0, 0.3);
  }

  .appointment-section::before {
    content: '';
    position: absolute;
    top: -4px;
    left: -4px;
    right: -4px;
    bottom: -4px;
    background: linear-gradient(45deg, rgba(255, 215, 0, 0.2), transparent, rgba(255, 215, 0, 0.2), transparent);
    z-index: -1;
    border-radius: 14px;
    animation: borderGlow 8s linear infinite;
  }

  @keyframes borderGlow {
    0% { background-position: 0% 0%; }
    100% { background-position: 300% 300%; }
  }

  .form-progress {
    display: flex;
    justify-content: space-between;
    position: relative;
    margin-bottom: 40px;
    max-width: 500px;
    margin-left: auto;
    margin-right: auto;
  }

  .form-progress::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 0;
    right: 0;
    height: 2px;
    background: rgba(255, 215, 0, 0.3);
    transform: translateY(-50%);
    z-index: 1;
  }
  
  .progress-step {
    width: 35px;
    height: 35px;
    background: #333;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ccc;
    font-weight: bold;
    border: 2px solid rgba(255, 215, 0, 0.3);
    position: relative;
    z-index: 2;
    transition: all 0.3s ease;
  }
  
  .progress-step.active {
    background: #ffd700;
    color: #b80000;
    border-color: #ffd700;
    transform: scale(1.2);
    box-shadow: 0 0 15px rgba(255, 215, 0, 0.5);
  }
  
  .progress-step.completed {
    background: #ffd700;
    color: #b80000;
  }

  .form-step {
    display: none;
  }
  
  .form-step.active {
    display: block;
    animation: fadeIn 0.5s forwards;
  }

  @keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
  }

  .form-group {
    margin-bottom: 25px;
    text-align: left;
    position: relative;
  }
  
  .form-group label {
    display: block;
    margin-bottom: 8px;
    color: #ffd700;
    font-weight: 600;
    font-size: 1rem;
    transition: all 0.3s ease;
  }
  
  .form-control {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid rgba(255, 215, 0, 0.3);
    border-radius: 8px;
    background: rgba(0, 0, 0, 0.2);
    color: white;
    font-size: 1rem;
    transition: all 0.3s ease;
  }
  
  .form-control:focus {
    border-color: #ffd700;
    box-shadow: 0 0 8px rgba(255, 215, 0, 0.4);
    outline: none;
  }
  
  .form-control::placeholder {
    color: #999;
  }

  .form-actions {
    display: flex;
    justify-content: space-between;
    margin-top: 30px;
  }

  .btn-prev {
    background: rgba(255, 255, 255, 0.1);
    color: #ccc;
    border: 1px solid rgba(255, 215, 0, 0.3);
    padding: 12px 25px;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
  }
  
  .btn-prev:hover {
    background: rgba(255, 215, 0, 0.1);
    color: #ffd700;
  }

  .btn-next, .submit-btn {
    background: linear-gradient(45deg, #ffd700, #ffec8b);
    color: #b80000;
    border: none;
    padding: 14px 32px;
    border-radius: 6px;
    font-weight: 700;
    font-size: 1.1rem;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
  }
  
  .btn-next:hover, .submit-btn:hover {
    background: linear-gradient(45deg, #ffec8b, #ffd700);
    transform: translateY(-3px);
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.3);
  }

  .input-icon {
    position: absolute;
    right: 15px;
    top: 45px;
    color: #ffd700;
    font-size: 1.1rem;
  }

  .date-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
    gap: 10px;
    margin-top: 15px;
  }
  
  .date-option {
    text-align: center;
    padding: 12px 5px;
    border: 1px solid rgba(255, 215, 0, 0.3);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
  }
  
  .date-option .day {
    font-weight: bold;
    font-size: 1.1rem;
    color: #fff;
  }
  
  .date-option .date {
    font-size: 0.85rem;
    color: #ccc;
  }
  
  .date-option:hover {
    background: rgba(255, 215, 0, 0.1);
    border-color: #ffd700;
  }
  
  .date-option.selected {
    background: rgba(255, 215, 0, 0.2);
    border-color: #ffd700;
    box-shadow: 0 0 10px rgba(255, 215, 0, 0.3);
  }

  .time-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
    gap: 10px;
    margin-top: 15px;
  }
  
  .time-option {
    text-align: center;
    padding: 12px 5px;
    border: 1px solid rgba(255, 215, 0, 0.3);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.95rem;
    color: #fff;
  }
  
  .time-option:hover {
    background: rgba(255, 215, 0, 0.1);
    border-color: #ffd700;
  }
  
  .time-option.selected {
    background: rgba(255, 215, 0, 0.2);
    border-color: #ffd700;
    box-shadow: 0 0 10px rgba(255, 215, 0, 0.3);
  }

  .confirmation-message {
    text-align: center;
    padding: 20px;
    margin: 20px 0;
    background: rgba(0, 128, 0, 0.2);
    border: 1px solid rgba(0, 128, 0, 0.3);
    border-radius: 8px;
    color: #4ceb95;
    display: none;
  }

  /* Tooltip */
  .tooltip {
    position: relative;
    display: inline-block;
    margin-left: 5px;
  }

  .tooltip-icon {
    width: 18px;
    height: 18px;
    background: #ffd700;
    color: #b80000;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    cursor: pointer;
  }

  .tooltip-text {
    position: absolute;
    top: -5px;
    transform: translateY(-100%);
    left: 50%;
    margin-left: -100px;
    width: 200px;
    background: rgba(0, 0, 0, 0.9);
    color: #fff;
    text-align: center;
    padding: 8px 12px;
    border-radius: 6px;
    z-index: 100;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.3s ease;
  }

  .tooltip-text::after {
    content: "";
    position: absolute;
    top: 100%;
    left: 50%;
    margin-left: -5px;
    border-width: 5px;
    border-style: solid;
    border-color: rgba(0, 0, 0, 0.9) transparent transparent transparent;
  }

  .tooltip:hover .tooltip-text {
    opacity: 1;
    visibility: visible;
  }

  /* Large Devices: min-width = 992px and max-width = 1199px */
  @media (min-width: 992px) and (max-width: 1199px) {
    .container {
      padding: 28px;
    }
    .services-grid {
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    }
  }

  /* Medium Devices: min-width = 768px and max-width = 991px */
  @media (min-width: 768px) and (max-width: 991px) {
    .container {
      padding: 24px 20px;
    }
    .page-title {
      font-size: 2.2rem;
    }
    .services-grid {
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 20px;
    }
  }

  /* Small Devices: min-width = 576px and max-width = 767px */
  @media (min-width: 576px) and (max-width: 767px) {
    .container {
      padding: 20px 15px;
    }
    .page-title {
      font-size: 1.8rem;
    }
    .services-grid {
      grid-template-columns: 1fr;
      gap: 18px;
    }
    .appointment-section {
      padding: 24px;
    }
  }

  /* Extra Small Devices: max-width = 575px */
  @media (max-width: 575px) {
    .container {
      padding: 16px 10px;
    }
    .page-title {
      font-size: 1.5rem;
    }
    .service-card, .appointment-section {
      padding: 18px;
    }
    .service-icon {
      font-size: 2.5rem;
    }
  }
</style>

<!-- Font Awesome for Icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<div class="container">
  <h1 class="page-title">Service Offer</h1>
  
  <div class="page-intro;">
    <p style = "color: #ffffff; text-align: center;">Trust your Mitsubishi to the experts. Our certified technicians use genuine parts and specialized equipment to keep your vehicle performing at its best.</p>
  </div>
  
  <div class="services-grid">
    <div class="service-card">
      <div class="service-icon"><i class="fas fa-wrench"></i></div>
      <div class="service-title">Regular Maintenance</div>
      <div class="service-description">
        Keep your Mitsubishi in peak condition with our comprehensive maintenance services, from oil changes to system checks.
      </div>
      <button class="service-btn"><i class="fas fa-calendar-check"></i> Book Service</button>
    </div>
    
    <div class="service-card">
      <div class="service-icon"><i class="fas fa-tools"></i></div>
      <div class="service-title">Repair Services</div>
      <div class="service-description">
        Expert diagnosis and repair using genuine Mitsubishi parts. We fix it right the first time, every time.
      </div>
      <button class="service-btn"><i class="fas fa-clock"></i> Schedule Repair</button>
    </div>
    
    <div class="service-card">
      <div class="service-icon"><i class="fas fa-ambulance"></i></div>
      <div class="service-title">Emergency Service</div>
      <div class="service-description">
        24/7 roadside assistance and priority service for emergencies. We're there when you need us most.
      </div>
      <button class="service-btn"><i class="fas fa-phone"></i> Emergency Call</button>
    </div>
    
    <div class="service-card">
      <div class="service-icon"><i class="fas fa-cog"></i></div>
      <div class="service-title">Parts & Accessories</div>
      <div class="service-description">
        Browse our selection of genuine Mitsubishi parts and accessories to maintain performance and value.
      </div>
      <button class="service-btn"><i class="fas fa-shopping-cart"></i> Browse Parts</button>
    </div>
    
    <div class="service-card">
      <div class="service-icon"><i class="fas fa-search"></i></div>
      <div class="service-title">Vehicle Inspection</div>
      <div class="service-description">
        Comprehensive multi-point inspections to identify potential issues before they become major problems.
      </div>
      <button class="service-btn"><i class="fas fa-clipboard-check"></i> Book Inspection</button>
    </div>
    
    <div class="service-card">
      <div class="service-icon"><i class="fas fa-bolt"></i></div>
      <div class="service-title">Express Service</div>
      <div class="service-description">
        Quick service for busy schedules. Basic maintenance completed in under 60 minutes while you wait.
      </div>
      <button class="service-btn"><i class="fas fa-stopwatch"></i> Express Booking</button>
    </div>
  </div>
    
      <!--<div class="form-actions">
          <div></div>  Empty div to maintain space-between alignment -->
       <!--   <button type="button" class="btn-next" id="to-step-2">Continue <i class="fas fa-arrow-right"></i></button>
        </div>
      </div>-->
      
      <!-- Step 2: Vehicle & Service -->
      <div class="form-step step-2">
        <h3 style="color: #ffd700; margin-bottom: 20px; text-align: center;">Vehicle & Service Details</h3>
        
        <div class="form-group">
          <label for="vehicle">Vehicle Model</label>
          <input type="text" id="vehicle" class="form-control" placeholder="e.g., Mitsubishi Mirage 2022" required>
          <span class="input-icon"><i class="fas fa-car"></i></span>
        </div>
        
        <div class="form-group">
          <label for="plate">License Plate Number</label>
          <input type="text" id="plate" class="form-control" placeholder="Enter your plate number" required>
          <span class="input-icon"><i class="fas fa-id-card"></i></span>
        </div>
        
        <div class="form-group">
          <label for="odometer">Odometer Reading (km)</label>
          <input type="number" id="odometer" class="form-control" placeholder="Current mileage" required>
          <span class="input-icon"><i class="fas fa-tachometer-alt"></i></span>
        </div>
        
        <div class="form-group">
          <label for="service-type">
            Service Type
            <span class="tooltip">
              <span class="tooltip-icon">?</span>
              <span class="tooltip-text">Select the primary reason for your visit</span>
            </span>
          </label>
          <select id="service-type" class="form-control" required>
            <option value="">Select Service Type</option>
            <option value="maintenance">Regular Maintenance</option>
            <option value="repair">Repair Service</option>
            <option value="inspection">Vehicle Inspection</option>
            <option value="express">Express Service</option>
            <option value="warranty">Warranty Service</option>
            <option value="other">Other (Specify in Notes)</option>
          </select>
          <span class="input-icon"><i class="fas fa-tools"></i></span>
        </div>
        
        <div class="form-actions">
          <button type="button" class="btn-prev" id="back-to-1"><i class="fas fa-arrow-left"></i> Back</button>
          <button type="button" class="btn-next" id="to-step-3">Schedule <i class="fas fa-arrow-right"></i></button>
        </div>
      </div>
      
      <!-- Step 3: Appointment Time -->
      <div class="form-step step-3">
        <h3 style="color: #ffd700; margin-bottom: 20px; text-align: center;">Choose Appointment Time</h3>
        
        <div class="form-group">
          <label>Select Date</label>
          <div class="date-grid">
            <div class="date-option">
              <div class="day">Mon</div>
              <div class="date">Jul 15</div>
            </div>
            <div class="date-option">
              <div class="day">Tue</div>
              <div class="date">Jul 16</div>
            </div>
            <div class="date-option">
              <div class="day">Wed</div>
              <div class="date">Jul 17</div>
            </div>
            <div class="date-option">
              <div class="day">Thu</div>
              <div class="date">Jul 18</div>
            </div>
            <div class="date-option">
              <div class="day">Fri</div>
              <div class="date">Jul 19</div>
            </div>
            <div class="date-option">
              <div class="day">Sat</div>
              <div class="date">Jul 20</div>
            </div>
            <div class="date-option">
              <div class="day">Mon</div>
              <div class="date">Jul 22</div>
            </div>
          </div>
        </div>
        
        <div class="form-group">
          <label>Select Time</label>
          <div class="time-grid">
            <div class="time-option">8:00 AM</div>
            <div class="time-option">9:00 AM</div>
            <div class="time-option">10:00 AM</div>
            <div class="time-option">11:00 AM</div>
            <div class="time-option">1:00 PM</div>
            <div class="time-option">2:00 PM</div>
            <div class="time-option">3:00 PM</div>
            <div class="time-option">4:00 PM</div>
          </div>
        </div>
        
        <div class="form-group">
          <label for="notes">Additional Notes</label>
          <textarea id="notes" class="form-control" rows="3" placeholder="Any specific concerns or requests..."></textarea>
        </div>
        
        <div class="form-actions">
          <button type="button" class="btn-prev" id="back-to-2"><i class="fas fa-arrow-left"></i> Back</button>
          <button type="submit" class="submit-btn">Confirm Appointment <i class="fas fa-check"></i></button>
        </div>
      </div>
      
      <div class="confirmation-message" id="confirmation">
        <i class="fas fa-check-circle" style="font-size: 3rem; color: #4ceb95; margin-bottom: 15px;"></i>
        <h3>Appointment Scheduled!</h3>
        <p>We have received your service request. A confirmation has been sent to your email.</p>
        <p>Reference #: <strong>SVC-20240715-001</strong></p>
      </div>
    </form>
  </div>
</div>

<script>
  // Responsive UI/UX Detection and Adaptation System
  function detectScreenSize() {
    const width = window.innerWidth;
    const body = document.body;
    
    // Remove all size classes
    body.classList.remove('xs-screen', 'sm-screen', 'md-screen', 'lg-screen', 'xl-screen');
    
    // Add appropriate size class
    if (width <= 575) {
      body.classList.add('xs-screen');
    } else if (width <= 767) {
      body.classList.add('sm-screen');
    } else if (width <= 991) {
      body.classList.add('md-screen');
    } else if (width <= 1199) {
      body.classList.add('lg-screen');
    } else {
      body.classList.add('xl-screen');
    }
    
    // Adjust services grid layout
    const servicesGrid = document.querySelector('.services-grid');
    if (servicesGrid) {
      if (width <= 767) {
        servicesGrid.style.gridTemplateColumns = '1fr';
      } else if (width <= 991) {
        servicesGrid.style.gridTemplateColumns = 'repeat(auto-fit, minmax(250px, 1fr))';
      } else if (width <= 1199) {
        servicesGrid.style.gridTemplateColumns = 'repeat(auto-fit, minmax(280px, 1fr))';
      } else {
        servicesGrid.style.gridTemplateColumns = 'repeat(auto-fit, minmax(300px, 1fr))';
      }
    }
    
    // Adjust appointment form width
    const appointmentForm = document.querySelector('.appointment-form');
    if (appointmentForm) {
      if (width <= 575) {
        appointmentForm.style.maxWidth = '100%';
        appointmentForm.style.padding = '0 10px';
      } else {
        appointmentForm.style.maxWidth = '500px';
        appointmentForm.style.padding = '0';
      }
    }
  }

  // Initialize on page load
  document.addEventListener('DOMContentLoaded', detectScreenSize);
  
  // Listen for window resize
  window.addEventListener('resize', detectScreenSize);

  // Multi-step form functionality
  document.addEventListener('DOMContentLoaded', function() {
    const toStep2Btn = document.getElementById('to-step-2');
    const toStep3Btn = document.getElementById('to-step-3');
    const backTo1Btn = document.getElementById('back-to-1');
    const backTo2Btn = document.getElementById('back-to-2');
    
    const step1 = document.querySelector('.step-1');
    const step2 = document.querySelector('.step-2');
    const step3 = document.querySelector('.step-3');
    
    const progressStep1 = document.querySelector('.progress-step.step-1');
    const progressStep2 = document.querySelector('.progress-step.step-2');
    const progressStep3 = document.querySelector('.progress-step.step-3');
    
    toStep2Btn.addEventListener('click', function() {
      step1.classList.remove('active');
      step2.classList.add('active');
      progressStep1.classList.add('completed');
      progressStep2.classList.add('active');
    });
    
    backTo1Btn.addEventListener('click', function() {
      step2.classList.remove('active');
      step1.classList.add('active');
      progressStep2.classList.remove('active');
      progressStep1.classList.remove('completed');
      progressStep1.classList.add('active');
    });
    
    toStep3Btn.addEventListener('click', function() {
      step2.classList.remove('active');
      step3.classList.add('active');
      progressStep2.classList.add('completed');
      progressStep3.classList.add('active');
    });
    
    backTo2Btn.addEventListener('click', function() {
      step3.classList.remove('active');
      step2.classList.add('active');
      progressStep3.classList.remove('active');
      progressStep2.classList.remove('completed');
      progressStep2.classList.add('active');
    });
    
    // Date selection
    const dateOptions = document.querySelectorAll('.date-option');
    dateOptions.forEach(option => {
      option.addEventListener('click', function() {
        dateOptions.forEach(opt => opt.classList.remove('selected'));
        this.classList.add('selected');
      });
    });
    
    // Time selection
    const timeOptions = document.querySelectorAll('.time-option');
    timeOptions.forEach(option => {
      option.addEventListener('click', function() {
        timeOptions.forEach(opt => opt.classList.remove('selected'));
        this.classList.add('selected');
      });
    });
    
    // Form submission
    const appointmentForm = document.querySelector('.appointment-form');
    const confirmation = document.getElementById('confirmation');
    
    appointmentForm.addEventListener('submit', function(e) {
      e.preventDefault();
      
      // Simulate form submission
      const submitBtn = document.querySelector('.submit-btn');
      submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
      submitBtn.disabled = true;
      
      setTimeout(() => {
        step3.style.display = 'none';
        confirmation.style.display = 'block';
        progressStep3.classList.add('completed');
      }, 1500);
    });
    
    // Service card hover effects
    const serviceCards = document.querySelectorAll('.service-card');
    serviceCards.forEach(card => {
      card.addEventListener('mouseenter', function() {
        serviceCards.forEach(c => {
          if (c !== this) {
            c.style.transform = 'scale(0.98)';
            c.style.opacity = '0.8';
          }
        });
      });
      
      card.addEventListener('mouseleave', function() {
        serviceCards.forEach(c => {
          c.style.transform = '';
          c.style.opacity = '1';
        });
      });
    });
  });
</script>

<?php include 'footer.php'; ?>
