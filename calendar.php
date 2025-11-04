<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Salon Appointments Calendar</title>
  <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">

  <style>
    body {
      font-family: 'Segoe UI', Tahoma, sans-serif;
      margin: 0;
      padding: 40px 10px;
      background: #f4f4f9;
      color: #333;
    }
    h2 {
      text-align: center;
      color: #b84dff;
      margin-bottom: 20px;
      font-size: 1.9rem;
    }
    #calendar {
      max-width: 1000px;
      margin: auto;
      background: #fff;
      padding: 20px;
      border-radius: 12px;
      box-shadow: 0 6px 18px rgba(0,0,0,0.1);
      transition: opacity 0.3s ease;
    }
    .back-link, .refresh-btn {
      display: inline-block;
      margin: 20px 10px;
      text-decoration: none;
      background: #b84dff;
      color: #fff;
      padding: 10px 18px;
      border-radius: 8px;
      font-size: 0.95rem;
      font-weight: 500;
      transition: background 0.3s ease;
      cursor: pointer;
    }
    .back-link:hover, .refresh-btn:hover {
      background: #9e3ad4;
    }
    /* Loader */
    .loader {
      display: none;
      text-align: center;
      margin: 20px 0;
    }
    .loader div {
      width: 12px;
      height: 12px;
      margin: 0 3px;
      background: #b84dff;
      border-radius: 50%;
      display: inline-block;
      animation: bounce 0.6s infinite alternate;
    }
    .loader div:nth-child(2) { animation-delay: 0.2s; }
    .loader div:nth-child(3) { animation-delay: 0.4s; }
    @keyframes bounce {
      to { transform: translateY(-8px); opacity: 0.7; }
    }
    /* Modal */
    .modal {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.5);
      justify-content: center;
      align-items: center;
      z-index: 999;
    }
    .modal-content {
      background: #fff;
      padding: 25px 30px;
      border-radius: 14px;
      max-width: 420px;
      width: 90%;
      box-shadow: 0 8px 24px rgba(0,0,0,0.2);
      animation: fadeIn 0.3s ease;
    }
    .modal-content h3 {
      margin-top: 0;
      color: #b84dff;
    }
    .modal-content input {
      width: 100%;
      padding: 8px;
      margin: 5px 0 10px;
      border: 1px solid #ccc;
      border-radius: 6px;
    }
    .modal-buttons {
      margin-top: 15px;
      text-align: right;
    }
    .btn {
      border: none;
      padding: 9px 16px;
      border-radius: 8px;
      cursor: pointer;
      font-size: 0.9rem;
      margin-left: 8px;
    }
    .close-btn { background: #6c757d; color: #fff; }
    .edit-btn { background: #17a2b8; color: #fff; }
    .delete-btn { background: #dc3545; color: #fff; }
    .save-btn { background: #28a745; color: #fff; }
    .btn:hover { opacity: 0.85; }
    @keyframes fadeIn {
      from {opacity: 0; transform: scale(0.9);}
      to {opacity: 1; transform: scale(1);}
    }
    /* Highlight today */
    .fc-day-today { background: #f3e6ff !important; }
    /* Event hover */
    .fc-event:hover { opacity: 0.85; transform: scale(1.02); transition: all 0.2s ease; }
  </style>
</head>
<body>

  <h2>📅 Salon Booking Calendar</h2>

  <!-- Loader -->
  <div class="loader" id="loader"><div></div><div></div><div></div></div>

  <!-- Calendar -->
  <div id="calendar"></div>

  <!-- Modal -->
  <div class="modal" id="eventModal">
    <div class="modal-content">
      <h3 id="eventTitle"></h3>
      <!-- View Mode -->
      <div id="viewMode">
        <p><strong>Date:</strong> <span id="eventDate"></span></p>
        <p><strong>Time:</strong> <span id="eventTime"></span></p>
      </div>
      <!-- Edit Mode -->
      <div id="editMode" style="display:none;">
        <label>Customer Name</label>
        <input type="text" id="editCustomer">
        <label>Service</label>
        <input type="text" id="editService">
        <label>Date</label>
        <input type="date" id="editDate">
        <label>Time</label>
        <input type="time" id="editTime">
      </div>
      <div class="modal-buttons">
        <button class="btn close-btn" onclick="closeModal()">Close</button>
        <button class="btn edit-btn" id="editBtn">Edit</button>
        <button class="btn save-btn" id="saveBtn" style="display:none;">Save</button>
        <button class="btn delete-btn" id="deleteBtn">Delete</button>
      </div>
    </div>
  </div>

  <!-- Buttons -->
  <div style="text-align:center;">
    <a href="index.php" class="back-link">⬅ Back to Dashboard</a>
    <button class="refresh-btn" onclick="refreshCalendar()">🔄 Refresh</button>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
  <script>
    let calendar;
    let currentEvent;

    document.addEventListener('DOMContentLoaded', function() {
      const calendarEl = document.getElementById('calendar');
      const loader = document.getElementById('loader');

      calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay' },
        events: { url: 'get_appointments.php', failure: () => alert('❌ Could not load appointments.') },
        eventColor: '#b84dff',
        eventTextColor: '#fff',
        nowIndicator: true,
        editable: false,
        dayMaxEvents: true,
        eventClick: function(info) {
          currentEvent = info.event;
          const start = currentEvent.start;

          document.getElementById('eventTitle').innerText = `${currentEvent.extendedProps.customer_name} - ${currentEvent.extendedProps.service}`;
          document.getElementById('eventDate').innerText = start.toLocaleDateString();
          document.getElementById('eventTime').innerText = start.toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});

          document.getElementById('editCustomer').value = currentEvent.extendedProps.customer_name;
          document.getElementById('editService').value = currentEvent.extendedProps.service;
          document.getElementById('editDate').value = start.toISOString().split("T")[0];
          document.getElementById('editTime').value = start.toISOString().split("T")[1].substring(0,5);

          document.getElementById('viewMode').style.display = 'block';
          document.getElementById('editMode').style.display = 'none';
          document.getElementById('editBtn').style.display = 'inline-block';
          document.getElementById('saveBtn').style.display = 'none';

          document.getElementById('eventModal').style.display = 'flex';
          document.getElementById('editCustomer').focus();
        },
        loading: function(isLoading) {
          loader.style.display = isLoading ? 'block' : 'none';
          calendarEl.style.opacity = isLoading ? 0.5 : 1;
        }
      });

      calendar.render();

      // Edit button
      document.getElementById('editBtn').addEventListener('click', () => {
        document.getElementById('viewMode').style.display = 'none';
        document.getElementById('editMode').style.display = 'block';
        document.getElementById('editBtn').style.display = 'none';
        document.getElementById('saveBtn').style.display = 'inline-block';
      });

      // Save button
      document.getElementById('saveBtn').addEventListener('click', () => {
        const id = currentEvent.id;
        const customer = document.getElementById('editCustomer').value;
        const service = document.getElementById('editService').value;
        const date = document.getElementById('editDate').value;
        const time = document.getElementById('editTime').value;

        fetch('update_appointment.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/x-www-form-urlencoded'},
          body: `id=${encodeURIComponent(id)}&customer_name=${encodeURIComponent(customer)}&service=${encodeURIComponent(service)}&appointment_date=${encodeURIComponent(date)}&appointment_time=${encodeURIComponent(time)}`
        })
        .then(res => res.text())
        .then(data => {
          alert(data);
          closeModal();
          refreshCalendar();
        });
      });

      // Delete button
      document.getElementById('deleteBtn').addEventListener('click', () => {
        if(confirm("Are you sure you want to delete this appointment?")) {
          fetch('delete_appointment.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `id=${encodeURIComponent(currentEvent.id)}`
          })
          .then(res => res.text())
          .then(data => {
            alert(data);
            closeModal();
            refreshCalendar();
          });
        }
      });
    });

    function closeModal() { document.getElementById('eventModal').style.display = 'none'; }
    function refreshCalendar() { if(calendar) calendar.refetchEvents(); }
  </script>
</body>
</html>
