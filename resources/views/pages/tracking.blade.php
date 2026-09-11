@extends('layouts.app')

@section('title', "Track Your Order")

@push('head')
<style>
        :root {
            --hyp-dark-1: #0f1113;
            --hyp-dark-2: #121316;
            --hyp-gray: #9aa0a6;
        }

        /* HERO */
        .tracking-hero {
            background: transparent;
            padding: 64px 20px;
            text-align: center;
            color: #fff;
        }

        .tracking-hero h1 {
            font-size: 44px;
            font-weight: 800;
            color: var(--primary-color);
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }

        .tracking-hero p {
            color: #c0c4c8;
            font-size: 16px;
            margin-bottom: 20px;
        }

        /* CARD */
        .tracking-card {
            background: linear-gradient(180deg, rgba(17, 17, 17, 0.9), rgba(14, 14, 14, 0.85));
            border-radius: 18px;
            padding: 28px;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.6);
            border: 1px solid rgba(255, 107, 0, 0.06);
        }

        .tracking-form {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 12px;
            align-items: center;
        }

        .tracking-input {
            background: transparent;
            border: 1px solid rgba(255, 255, 255, 0.06);
            padding: 14px 16px;
            border-radius: 12px;
            color: #fff;
            font-size: 15px;
            width: 100%;
        }

        .tracking-input::placeholder {
            color: #7f8488;
        }

        .tracking-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 6px 18px rgba(255, 107, 0, 0.12);
        }

        .btn-track {
            background: linear-gradient(135deg, var(--primary-color), #ff8533);
            color: #fff;
            border: none;
            padding: 12px 20px;
            border-radius: 12px;
            font-weight: 700;
            display: inline-flex;
            gap: 8px;
            align-items: center;
            cursor: pointer;
            transition: transform .15s ease, box-shadow .15s ease;
        }

        .btn-track:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 28px rgba(255, 107, 0, 0.18);
        }

        /* LOADING */
        .loading {
            display: flex;
            gap: 10px;
            align-items: center;
            justify-content: center;
            color: var(--primary-color);
            font-weight: 700;
            margin-top: 14px;
        }

        .loading .dot {
            width: 8px;
            height: 8px;
            background: var(--primary-color);
            border-radius: 50%;
            animation: blink 1s infinite;
        }

        .loading .dot:nth-child(2) {
            animation-delay: .15s;
        }

        .loading .dot:nth-child(3) {
            animation-delay: .3s;
        }

        @keyframes blink {
            0% {
                opacity: .2
            }

            50% {
                opacity: 1
            }

            100% {
                opacity: .2
            }
        }

        /* SHIPMENT TABS */
        .shipment-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .shipment-tab {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.08);
            padding: 10px 16px;
            border-radius: 10px;
            color: #9aa0a6;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
            font-weight: 600;
        }

        .shipment-tab:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        .shipment-tab.active {
            background: rgba(255, 107, 0, 0.15);
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        .shipment-tab-badge {
            background: var(--primary-color);
            color: white;
            border-radius: 12px;
            padding: 2px 8px;
            font-size: 12px;
            margin-left: 6px;
        }

        /* RESULT */
        .tracking-result {
            margin-top: 22px;
            background: linear-gradient(180deg, rgba(12, 12, 12, 0.9), rgba(10, 10, 10, 0.85));
            padding: 18px;
            border-radius: 14px;
            color: #e6e9eb;
            border: 1px solid rgba(255, 107, 0, 0.04);
        }

        .result-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 15px;
        }

        .result-left h5 {
            color: var(--primary-color);
            margin: 0;
            font-weight: 800;
            font-size: 16px;
        }

        .result-left p {
            margin: 0;
            color: #adb2b6;
            font-size: 13px;
        }

        .result-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .action-btn {
            background: transparent;
            border: 1px solid rgba(255, 255, 255, 0.06);
            color: #fff;
            padding: 8px 10px;
            border-radius: 10px;
            font-size: 13px;
            cursor: pointer;
        }

        .action-btn:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        /* SHIPMENT INFO */
        .shipment-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
            padding: 15px;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 10px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .info-label {
            color: #9aa0a6;
            font-size: 12px;
            font-weight: 600;
        }

        .info-value {
            color: #fff;
            font-size: 14px;
            font-weight: 700;
        }

        .provider-badge {
            background: linear-gradient(135deg, var(--primary-color), #ff8533);
            color: white;
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            display: inline-block;
        }

        /* PROGRESS */
        .progress-line {
            margin: 15px 0;
            height: 10px;
            background: rgba(255, 255, 255, 0.04);
            border-radius: 10px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, rgba(255, 107, 0, 0.95), rgba(255, 133, 51, 0.95));
            width: 0%;
            transition: width .8s ease;
        }

        /* TIMELINE */
        .timeline {
            margin-top: 18px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .timeline-item {
            display: flex;
            gap: 12px;
            align-items: flex-start;
        }

        .timeline-bullet {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.06);
            margin-top: 6px;
            flex-shrink: 0;
            transition: all .25s ease;
        }

        .timeline-item.active .timeline-bullet {
            background: var(--primary-color);
            box-shadow: 0 6px 18px rgba(255, 107, 0, 0.12);
            transform: scale(1.12);
        }

        .timeline-content p {
            margin: 0;
            color: #e0e4e6;
            font-weight: 700;
        }

        .timeline-content small {
            color: #9aa0a6;
        }

        /* EMPTY STATE */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #9aa0a6;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            color: var(--primary-color);
        }

        /* ERROR STATE */
        .error-state {
            text-align: center;
            padding: 30px 20px;
            color: #ff6b6b;
        }

        .error-state i {
            font-size: 36px;
            margin-bottom: 10px;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .tracking-hero h1 {
                font-size: 30px;
            }

            .tracking-card {
                padding: 18px;
                border-radius: 12px;
            }

            .tracking-form {
                grid-template-columns: 1fr;
            }

            .btn-track {
                width: 100%;
                justify-content: center;
            }

            .result-left h5 {
                font-size: 14px;
            }

            .tracking-result {
                padding: 14px;
                border-radius: 10px;
            }

            .shipment-info {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .shipment-tabs {
                overflow-x: auto;
                flex-wrap: nowrap;
                padding-bottom: 5px;
            }
        }
    </style>
@endpush

@section('content')
<div class="no-bottom no-top" id="content">
            <div id="top"></div>
                <section class="bg-dark text-light relative jarallax">
    <div class="de-gradient-edge-top"></div>
    <img src="{{ content_image('tracking.image.1', 'assets/images/2345.png') }}" class="jarallax-img" alt="">
    <div class="container relative z-2">
        <div class="row gy-4 gx-5 justify-content-center">
            <div class="col-lg-12 text-center">
                <div class="spacer-double sm-hide"></div>
                <h1 class="mb-3 wow fadeInUp" data-wow-delay=".2s">
                    {{ content('tracking.text.1', 'Track Your Order') }}
                </h1>
                <div class="border-bottom mb-3"></div>
                <ul class="crumb wow fadeInUp">
                    <li><a href="{{ \App\Support\Toko::url('/') }}">Home</a></li>
                    <li class="active">{{ content('tracking.text.2', 'Track Your Order') }}</li>
                </ul>
            </div>
        </div>
    </div>

    <div class="sw-overlay"></div>
</section>

    <!-- HERO -->
    <div class="tracking-hero">
        <h1>{{ content('tracking.text.3', 'Real Time Tracking Orders') }}</h1>
        <p>{{ content('tracking.text.4', 'Enter your Order Number below to view the latest shipment status.') }}</p>
    </div>

    <!-- TRACKING -->
    <section class="py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="tracking-card">

                        <!-- FORM -->
                        <form id="trackingForm" class="tracking-form" autocomplete="off" novalidate>
                            <input id="trackingNumber" class="tracking-input" type="text"
                                placeholder="e.g. HSTXXXXXXXXXXXXX" aria-label="Tracking Number" required>
                            <button class="btn-track" type="submit" aria-label="Track now">
                                <i class="fas fa-search"></i><span>{{ content('tracking.text.5', 'Track') }}</span>
                            </button>
                        </form>

                        <!-- loading -->
                        <div id="loading" class="loading d-none">
                            <div class="dot"></div>
                            <div class="dot"></div>
                            <div class="dot"></div>
                            <span>{{ content('tracking.text.6', 'Fetching tracking details...') }}</span>
                        </div>

                        <!-- result -->
                        <div id="trackingResult" class="tracking-result d-none" role="region" aria-live="polite">
                            <!-- Shipment Tabs -->
                            <div id="shipmentTabs" class="shipment-tabs d-none"></div>

                            <!-- Order Info -->
                            <div id="orderInfo" class="d-none">
                                <div class="result-header">
                                    <div class="result-left">
                                        <h5 id="resultTitle"><i class="fas fa-boxes"></i> Order Tracking</h5>
                                        <p id="resultSub">{{ content('tracking.text.7', 'Multiple shipments found for your order') }}</p>
                                    </div>
                                    <div class="result-actions">
                                        <button id="copyBtn" class="action-btn" title="Copy order number"><i
                                                class="fas fa-copy"></i></button>
                                        <button id="openWhatsApp" class="action-btn" title="Contact support via WhatsApp"><i
                                                class="fab fa-whatsapp"></i></button>
                                    </div>
                                </div>
                            </div>

                            <!-- Shipment Content -->
                            <div id="shipmentContent">
                                <!-- Shipment details will be loaded here -->
                            </div>

                        </div>

                    </div>
                </div>
            </div>
        </div>
    </section>
        </div>
@endsection

@push('scripts')
<script>
        (function() {
            const form = document.getElementById('trackingForm');
            const input = document.getElementById('trackingNumber');
            const loading = document.getElementById('loading');
            const resultBox = document.getElementById('trackingResult');
            const shipmentTabs = document.getElementById('shipmentTabs');
            const orderInfo = document.getElementById('orderInfo');
            const shipmentContent = document.getElementById('shipmentContent');
            const copyBtn = document.getElementById('copyBtn');
            const openWhatsApp = document.getElementById('openWhatsApp');

            let currentShipments = [];
            let activeTabIndex = 0;

            function showLoading() {
                loading.classList.remove('d-none');
                resultBox.classList.add('d-none');
                shipmentTabs.classList.add('d-none');
                orderInfo.classList.add('d-none');
            }

            function hideLoading() {
                loading.classList.add('d-none');
            }

            function renderShipmentTabs(shipments) {
                shipmentTabs.innerHTML = '';

                shipments.forEach((shipment, index) => {
                    const tab = document.createElement('button');
                    tab.className = `shipment-tab ${index === activeTabIndex ? 'active' : ''}`;
                    tab.innerHTML = `
                        <i class="fas fa-shipping-fast"></i>
                        ${shipment.provider || 'Unknown'}
                        <span class="shipment-tab-badge">${shipment.tracking_number}</span>
                    `;
                    tab.addEventListener('click', () => switchShipmentTab(index));
                    shipmentTabs.appendChild(tab);
                });

                shipmentTabs.classList.remove('d-none');
            }

            function switchShipmentTab(index) {
                activeTabIndex = index;

                // Update active tab
                document.querySelectorAll('.shipment-tab').forEach((tab, i) => {
                    tab.classList.toggle('active', i === index);
                });

                // Render shipment content
                renderShipmentContent(currentShipments[index]);
            }

            function renderShipmentContent(shipment) {
                if (!shipment) return;

                const events = shipment.events || [];
                const totalEvents = events.length;
                const progressPercent = Math.min(100, Math.round((totalEvents / 5) * 100));

                // Format last checked date
                const lastChecked = shipment.last_checked ?
                    new Date(shipment.last_checked).toLocaleString() :
                    'Just now';

                // Format status updated date
                const statusUpdated = shipment.status_updated_at ?
                    new Date(shipment.status_updated_at).toLocaleString() :
                    '-';

                shipmentContent.innerHTML = `
                    <div class="shipment-info">
                        <div class="info-item">
                            <span class="info-label">Tracking Number</span>
                            <span class="info-value">${shipment.tracking_number}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Carrier</span>
                            <span class="provider-badge">${shipment.provider}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Current Status</span>
                            <span class="info-value" style="color: var(--primary-color); text-transform: capitalize;">
                                ${(shipment.status || 'Unknown').toLowerCase().replace(/_/g, ' ')}
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Status Description</span>
                            <span class="info-value">${shipment.status_description || 'No description available'}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Status Updated</span>
                            <span class="info-value">${statusUpdated}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Last Checked</span>
                            <span class="info-value">${lastChecked}</span>
                        </div>
                    </div>

                    <div class="progress-line" aria-hidden="true">
                        <div class="progress-fill" style="width: ${progressPercent}%"></div>
                    </div>

                    ${events.length > 0 ? `
                                                            <h6 style="color: var(--primary-color); margin: 20px 0 10px 0;">
                                                                <i class="fas fa-list-alt"></i> Tracking History
                                                            </h6>
                                                            <ul class="timeline">
                                                                ${events.map((event, index) => `
                                <li class="timeline-item ${index === 0 ? 'active' : ''}">
                                    <div class="timeline-bullet"></div>
                                    <div class="timeline-content">
                                        <p>${event.status || event.description || 'Status Update'}</p>
                                        <small>
                                            ${event.timestamp ? new Date(event.timestamp).toLocaleString() : ''}
                                            ${event.location && event.location.address ?
                                                ` • ${event.location.address.addressLocality || ''}` :
                                                ''}
                                        </small>
                                        ${event.description ? `<br><small style="color: #9aa0a6">${event.description}</small>` : ''}
                                    </div>
                                </li>
                            `).reverse().join('')}
                                                            </ul>
                                                        ` : `
                                                            <div class="empty-state">
                                                                <i class="fas fa-box-open"></i>
                                                                <h5>No Tracking Events</h5>
                                                                <p>No tracking information available for this shipment yet.</p>
                                                            </div>
                                                        `}
                `;
            }

            function renderResult(orderNumber, data) {
                currentShipments = data.shipments || [];

                if (currentShipments.length === 0) {
                    renderNoShipments(orderNumber);
                    return;
                }

                // Show order info
                orderInfo.classList.remove('d-none');
                document.getElementById('resultTitle').innerHTML =
                    `<i class="fas fa-boxes"></i> Order: ${data.order_ref_number || orderNumber}`;
                document.getElementById('resultSub').textContent =
                    `${currentShipments.length} shipment${currentShipments.length > 1 ? 's' : ''} found`;

                // Render tabs and content
                renderShipmentTabs(currentShipments);
                renderShipmentContent(currentShipments[activeTabIndex]);

                resultBox.classList.remove('d-none');
            }

            function renderNoShipments(orderNumber) {
                orderInfo.classList.remove('d-none');
                document.getElementById('resultTitle').innerHTML =
                    `<i class="fas fa-box"></i> Order: ${orderNumber}`;
                document.getElementById('resultSub').textContent = 'No shipments found';

                shipmentContent.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-shipping-fast"></i>
                        <h5>No Shipments Found</h5>
                        <p>No shipment information is available for this order yet.</p>
                    </div>
                `;

                resultBox.classList.remove('d-none');
            }

            function renderError(message) {
                orderInfo.classList.remove('d-none');
                document.getElementById('resultTitle').innerHTML =
                    `<i class="fas fa-exclamation-triangle"></i> Tracking Error`;
                document.getElementById('resultSub').textContent = 'Unable to fetch tracking information';

                shipmentContent.innerHTML = `
                    <div class="error-state">
                        <i class="fas fa-exclamation-circle"></i>
                        <h5>Unable to Track</h5>
                        <p>${message}</p>
                    </div>
                `;

                resultBox.classList.remove('d-none');
            }

            function parseApiResponse(apiData) {
                const shipments = apiData.shipments || [];

                shipments.forEach(shipment => {
                    // Extract events from DHL response structure
                    if (shipment.tracking_details &&
                        shipment.tracking_details.shipments &&
                        shipment.tracking_details.shipments.length > 0) {

                        const dhlShipment = shipment.tracking_details.shipments[0];
                        shipment.events = dhlShipment.events || [];
                    } else {
                        shipment.events = [];
                    }
                });

                return {
                    order_ref_number: apiData.order_ref_number,
                    shipments: shipments
                };
            }

            form.addEventListener('submit', async (e) => {
                e.preventDefault();

                const orderNumber = input.value.trim();
                if (!orderNumber) return;

                showLoading();

                try {
                    const token = document.querySelector("meta[name='csrf-token']").getAttribute("content");
                    const response = await fetch("/tracking/track", {
                        method: "POST",
                        body: new URLSearchParams({
                            "order_number": orderNumber
                        }),
                        credentials: "same-origin",
                        headers: {
                            "Content-Type": "application/x-www-form-urlencoded",
                            "X-CSRF-TOKEN": token,
                        },
                    });

                    const data = await response.json();

                    if (data.status === 'success') {
                        const parsedData = parseApiResponse(data.data);
                        renderResult(orderNumber, parsedData);
                    } else {
                        renderError(data.message || 'Failed to fetch tracking information');
                    }
                } catch (error) {
                    console.error('Tracking error:', error);
                    renderError('Network error. Please check your connection and try again.');
                } finally {
                    hideLoading();
                }
            });

            copyBtn.addEventListener('click', function() {
                const orderNumber = input.value.trim();
                if (!orderNumber) return;

                navigator.clipboard.writeText(orderNumber).then(() => {
                    copyBtn.innerHTML = '<i class="fas fa-check"></i>';
                    setTimeout(() => copyBtn.innerHTML = '<i class="fas fa-copy"></i>', 1500);
                });
            });

            openWhatsApp.addEventListener('click', function() {
                const phone = '62895337161221';
                if (phone && phone.length > 0) {
                    const orderNumber = input.value.trim();
                    const text = encodeURIComponent(
                        `Hi Hypersonic, I need help with order tracking: ${orderNumber}`);
                    window.open(`https://wa.me/${phone}?text=${text}`, '_blank');
                }
            });
        })();
    </script>
@endpush
