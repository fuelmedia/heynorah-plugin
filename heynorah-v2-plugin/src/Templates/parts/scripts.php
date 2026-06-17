<?php
/**
 * Template part: Scripts
 * All JavaScript functionality:
 * - Accordion initialization
 * - Fancybox initialization
 * - Loan Calculator
 * - Embla Carousel
 * - Chart.js Integration
 */
?>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Initialize accordion
        const accordionEl = document.getElementById('accordion-flush');
        if (accordionEl) {
            const accordionButtons = accordionEl.querySelectorAll('[data-accordion-target]');

            accordionButtons.forEach(button => {
                button.addEventListener('click', function () {
                    const targetId = this.getAttribute('data-accordion-target');
                    const targetEl = document.querySelector(targetId);
                    const isExpanded = this.getAttribute('aria-expanded') === 'true';

                    // Close all other accordion items
                    accordionButtons.forEach(btn => {
                        if (btn !== button) {
                            btn.setAttribute('aria-expanded', 'false');
                            const otherTargetId = btn.getAttribute('data-accordion-target');
                            const otherTargetEl = document.querySelector(otherTargetId);
                            if (otherTargetEl) {
                                otherTargetEl.style.display = 'none';
                            }
                            const otherIcon = btn.querySelector('svg');
                            if (otherIcon) {
                                otherIcon.classList.remove('rotate-180');
                            }
                        }
                    });

                    // Toggle current accordion item
                    if (isExpanded) {
                        this.setAttribute('aria-expanded', 'false');
                        targetEl.style.display = 'none';
                        const icon = this.querySelector('svg');
                        if (icon) {
                            icon.classList.remove('rotate-180');
                        }
                    } else {
                        this.setAttribute('aria-expanded', 'true');
                        targetEl.style.display = 'block';
                        const icon = this.querySelector('svg');
                        if (icon) {
                            icon.classList.add('rotate-180');
                        }
                    }
                });
            });
        }
    });
</script>


    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.css" />
<script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.umd.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script src="https://unpkg.com/embla-carousel/embla-carousel.umd.js"></script>
<script src="https://unpkg.com/embla-carousel-autoplay/embla-carousel-autoplay.umd.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        
        // Initialize Fancybox
        if (window.Fancybox) {
            Fancybox.bind("[data-fancybox]", {
                Hash: false,
                Thumbs: {
                    autoStart: false,
                },
            });
        }

        // Loan Calculator
        const calcBtn = document.getElementById('lc-calculate');
        const amountInput = document.getElementById('lc-amount');
        const taxInput = document.getElementById('lc-tax');
        const downInput = document.getElementById('lc-down');
        const rateInput = document.getElementById('lc-rate');
        const termInput = document.getElementById('lc-term');

        // Auto-calculate on input change
        if (amountInput) amountInput.addEventListener('input', calculateLoan);
        if (taxInput) taxInput.addEventListener('input', calculateLoan);
        if (downInput) downInput.addEventListener('input', calculateLoan);
        if (rateInput) rateInput.addEventListener('input', calculateLoan);
        if (termInput) termInput.addEventListener('input', calculateLoan);

        let loanChart = null;

        function initCalculator() {
            if (typeof Chart === 'undefined') {
                setTimeout(initCalculator, 250);
                return;
            }
            calculateLoan();
        }
        
        // Start initialization check
        initCalculator();

        function calculateLoan() {
            const amountInput = document.getElementById('lc-amount');
            const taxInput = document.getElementById('lc-tax');
            const downInput = document.getElementById('lc-down');
            const rateInput = document.getElementById('lc-rate');
            const termInput = document.getElementById('lc-term');

            if (!amountInput || !taxInput || !downInput || !rateInput || !termInput) return;

            const price = parseFloat(amountInput.value) || 0;
            const taxRate = parseFloat(taxInput.value) || 0;
            const downPayment = parseFloat(downInput.value) || 0;
            const rateVal = parseFloat(rateInput.value) || 0;
            const termMonths = parseFloat(termInput.value) || 0;

            // Calculate total price with tax
            const taxAmount = price * (taxRate / 100);
            const totalWithTax = price + taxAmount;
            
            // Calculate financed amount (after down payment)
            const financedAmount = Math.max(0, totalWithTax - downPayment);
            
            const monthlyRate = (rateVal / 100) / 12;

            let monthlyPayment = 0;
            let totalInterest = 0;
            let totalPrincipal = financedAmount;

            if (financedAmount > 0 && termMonths > 0) {
                 if (monthlyRate > 0) {
                    monthlyPayment = (financedAmount * monthlyRate * Math.pow(1 + monthlyRate, termMonths)) / (Math.pow(1 + monthlyRate, termMonths) - 1);
                    const totalPaid = monthlyPayment * termMonths;
                    totalInterest = totalPaid - financedAmount;
                 } else {
                    monthlyPayment = financedAmount / termMonths;
                    totalInterest = 0;
                 }
            }

            const fmt = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 2 });
            const fmtNoDecimal = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 0 });

            // Update summary fields
            const summaryPrice = document.getElementById('lc-summary-price');
            const summaryTax = document.getElementById('lc-summary-tax');
            const summaryDown = document.getElementById('lc-summary-down');
            const summaryRate = document.getElementById('lc-summary-rate');
            const summaryTerm = document.getElementById('lc-summary-term');
            const monthlyEl = document.getElementById('lc-monthly-payment');

            if(summaryPrice) summaryPrice.textContent = fmtNoDecimal.format(price);
            if(summaryTax) summaryTax.textContent = taxRate.toFixed(2) + ' %';
            if(summaryDown) summaryDown.textContent = fmtNoDecimal.format(downPayment);
            if(summaryRate) summaryRate.textContent = rateVal.toFixed(2) + ' %';
            if(summaryTerm) summaryTerm.textContent = termMonths + ' Months';
            if(monthlyEl) monthlyEl.textContent = fmt.format(monthlyPayment);

            // Update chart text
            const chartText = document.getElementById('lc-chart-text-val');
            if(chartText) chartText.textContent = fmt.format(monthlyPayment);

            // Update chart visualization
            updateChart(totalPrincipal, totalInterest);
        }

        function updateChart(principal, interest) {
            const ctx = document.getElementById('lc-chart');
            if (!ctx) return;

            if (loanChart) {
                loanChart.destroy();
            }
            
            if (typeof Chart === 'undefined') return;

            loanChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Principal', 'Interest'],
                    datasets: [{
                        data: [principal, interest],
                        backgroundColor: ['#10b981', '#3b82f6'],
                        borderWidth: 0,
                        hoverOffset: 4
                    }]
                },
                options: {
                    cutout: '80%',
                    plugins: {
                        legend: { display: false },
                        tooltip: { 
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.parsed !== null) {
                                        label += new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(context.parsed);
                                    }
                                    return label;
                                }
                            }
                        }
                    },
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        }

        
        function initEmbla() {
            // Check if libraries are loaded
            if (!window.EmblaCarousel) {
                console.warn('EmblaCarousel not loaded yet, retrying...');
                setTimeout(initEmbla, 100);
                return;
            }

            const Autoplay = window.EmblaCarouselAutoplay || (window.EmblaCarouselAutoplay ? window.EmblaCarouselAutoplay.default : null);
            
            // Main Gallery Slider
            const mainEmblaNode = document.querySelector('.embla');
            if (mainEmblaNode) {
                const options = { loop: true };
                const plugins = [];
                if (Autoplay) plugins.push(Autoplay({ delay: 5000, stopOnInteraction: false }));

                const mainEmblaApi = EmblaCarousel(mainEmblaNode, options, plugins);
                
                // Navigation
                const prevBtn = document.querySelector('.embla__button.embla__prev');
                const nextBtn = document.querySelector('.embla__button.embla__next');
                
                if (prevBtn) {
                    prevBtn.addEventListener('click', (e) => {
                        e.stopPropagation(); 
                        mainEmblaApi.scrollPrev();
                    });
                }
                if (nextBtn) {
                    nextBtn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        mainEmblaApi.scrollNext();
                    });
                }
            }

            // Related Items Slider
            const relatedEmblaNode = document.querySelector('.embla-related');
            if (relatedEmblaNode) {
                const options = { 
                    loop: true, 
                    align: 'start', 
                    slidesToScroll: 1,
                    containScroll: 'trimSnaps'
                };
                const plugins = [];
                if (Autoplay) plugins.push(Autoplay({ delay: 6000 }));

                const relatedEmblaApi = EmblaCarousel(relatedEmblaNode, options, plugins);
                
                const prevBtn = document.querySelector('.embla-related__prev');
                const nextBtn = document.querySelector('.embla-related__next');
                
                if (prevBtn) prevBtn.addEventListener('click', () => relatedEmblaApi.scrollPrev());
                if (nextBtn) nextBtn.addEventListener('click', () => relatedEmblaApi.scrollNext());
            }
        }

        initEmbla();
    });
</script>
