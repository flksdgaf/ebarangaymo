<div class="request-container">
  <div class="request-card request-full-width-card">
  <h3 style="color: #61AD41; margin-bottom: 10px;">Barangay ID</h3>
        <table>
        <thead>
            <tr>
                <th>Transaction No.</th>
                <th>Name</th>
                <th>Address</th>
                <th>Purok</th>
                <th>Height</th>
                <th>Weight</th>
                <th>Birthdate</th>
                <th>Birthplace</th>
                <th>Status</th>
                <th>Religion</th>
                <th>Contact Person</th>
                <th>Payment Method</th>
                <th>Transaction Type</th>
                <th>Payment Status</th>
                <th>Document Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php
                $query = "SELECT * FROM barangay_id_requests";
                $result = mysqli_query($conn, $query);
                if (!$result) {
                    die("Query failed: " . mysqli_error($conn));
                }
                else    
                {
                    while ($row = mysqli_fetch_assoc($result)) {
                        ?>
                         <tr>
                            <td><?php echo $row['transaction_id']; ?></td>
                            <td><?php echo $row['full_name']; ?></td>
                            <td><?php echo $row['full_address']; ?></td>
                            <td><?php echo $row['purok']; ?></td>
                            <td><?php echo $row['height']; ?></td>
                            <td><?php echo $row['weight']; ?></td>
                            <td><?php echo $row['birthdate']; ?></td>
                            <td><?php echo $row['birthplace']; ?></td>
                            <td><?php echo $row['status']; ?></td>
                            <td><?php echo $row['religion']; ?></td>
                            <td><?php echo $row['contact_person']; ?></td>   
                            <td><?php echo $row['payment_method']; ?></td>   
                            <td><?php echo $row['transaction_type']; ?></td>
                            <td><?php echo $row['payment_status']; ?></td>
                            <td><?php echo $row['document_status']; ?></td>  
                            <td>
                                <div class="dropdown">
                                    <button class="action-btn" onclick="toggleDropdown(this)">
                                    Action <span class="arrow">&#9662;</span>
                                    </button>
                                    <div class="dropdown-menu">
                                    <a href="#" onclick='openModal(<?php echo json_encode($row); ?>, "view")'>View</a>
                                    <a href="#" onclick='openModal(<?php echo json_encode($row); ?>, "edit")'>Edit</a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php
                    }
                }   
            ?>  
        </tbody>
        </table>

        <!-- View/Edit Modal -->
        <div id="requestModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2 id="modalTitle">Request Info</h2>
            <div id="modalBody">
            <!-- Details will be inserted here -->
            </div>
        </div>
        </div>

        <!-- Add some CSS styles for the modal -->
        <style>
        .modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 999;
        background-color: rgba(0,0,0,0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        }
        .modal-content {
        background-color: #fff;
        padding: 30px;
        width: 90%;
        max-width: 700px;
        border-radius: 15px;
        font-family: 'Segoe UI', sans-serif;
        box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        position: relative;
        max-height: 90vh;
        overflow-y: auto;
        }
        .modal .close {
        position: absolute;
        top: 15px;
        right: 20px;
        font-size: 24px;
        cursor: pointer;
        }

        .modal h3 {
        margin-top: 20px;
        margin-bottom: 10px;
        font-size: 1.1rem;
        }

        .modal p {
        margin: 4px 0;
        }
        </style>

  </div>
</div>

<script>
function openModal(data, type) {
  document.getElementById("requestModal").style.display = "flex";
  document.getElementById("modalTitle").textContent = (type === "edit") ? "Edit Request" : "Request Details";

  const editable = type === "edit";

  const grouped = `
    <div style="margin-bottom: 20px;">
      <h3 style="color: #14532d; border-left: 5px solid #61AD41; padding-left: 10px;"></h3>
      <div style="display: flex; flex-direction: column; gap: 5px; margin-left: 10px;">
        <p><strong>Transaction No.:</strong> ${field(data.transaction_id)}</p>
        <p><strong>Requested by:</strong> ${field(data.full_name)}</p>
        <p><strong>Requested Service:</strong> Barangay ID</p>
        <p><strong>Request Date:</strong> ${field(data.request_date)}</p>
      </div>
    </div>

    <div style="margin-bottom: 20px;">
      <h3 style="color: #14532d; border-left: 5px solid #61AD41; padding-left: 10px;"></h3>
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-left: 10px;">
        <p><strong>Address:</strong> ${field(data.full_address)}</p>
        <p><strong>Purok:</strong> ${field(data.purok)}</p>
        <p><strong>Birthdate:</strong> ${field(data.birthdate)}</p>
        <p><strong>Birthplace:</strong> ${field(data.birthplace)}</p>
        <p><strong>Status:</strong> ${field(data.status)}</p>
        <p><strong>Religion:</strong> ${field(data.religion)}</p>
        <p><strong>Height:</strong> ${field(data.height)}</p>
        <p><strong>Weight:</strong> ${field(data.weight)}</p>
        <p><strong>Contact Person:</strong> ${field(data.contact_person)}</p>
      </div>
    </div>

    <div style="margin-bottom: 20px;">
      <h3 style="color: #14532d; border-left: 5px solid #61AD41; padding-left: 10px;"></h3>
      <div style="display: flex; flex-direction: column; gap: 5px; margin-left: 10px;">
        <p><strong>Transaction Type:</strong> ${field(data.transaction_type)}</p>
        <p><strong>Payment Status:</strong> ${field(data.payment_status)}</p>
        <p><strong>Document Status:</strong> ${field(data.document_status)}</p>
      </div>
    </div>

    ${editable ? `
      <div style="text-align: right; margin-top: 20px;">
        <button onclick="submitUpdate()" style="padding: 10px 20px; background-color: #61AD41; color: white; border: none; border-radius: 5px; cursor: pointer;">Update</button>
      </div>
    ` : ''}
  `;

  document.getElementById("modalBody").innerHTML = grouped;

  function field(value) {
    return editable ? `<input type="text" value="${value}" style="width: 100%; padding: 5px;" />` : value;
  }
}

function closeModal() {
  document.getElementById("requestModal").style.display = "none";
}

// Placeholder function for the update button
function submitUpdate() {
  alert("Update button clicked! Implement form submission logic here.");
}


</script>
