<?php
/**
 * Template part: Loan Calculator
 * Interactive loan payment calculator with chart visualization
 * 
 * @var float $loan_default_amount
 */
?>

<!-- Finance Section -->
<section class="my-16 py-16 bg-gray-50 dark:bg-zinc-900/50">
    <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 mb-12" id="loan-calculator">
            <!-- Calculator Inputs -->
            <div>
                <h3 class="text-3xl font-light text-slate-900 dark:text-white mb-6">Finance</h3>
                <p class="text-gray-600 dark:text-gray-400 mb-4 leading-relaxed">Curious what financing on this inventory
                    might look like? Enter the price, your
                    down payment, and your preferred term to estimate a monthly payment.</p>
                <p class="text-gray-500 dark:text-gray-500 text-sm mb-8 leading-relaxed">This calculator is provided
                    as a convenience and is based on example rates.
                    For current rates, exact payment amounts, and available programs, please contact our finance
                    department.</p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Price of Inventory Item -->
                    <div>
                        <div
                            class="border border-gray-200 dark:border-zinc-700 px-4 py-3 bg-white dark:bg-zinc-900 focus-within:border-emerald-500 dark:focus-within:border-emerald-500 focus-within:ring-1 focus-within:ring-emerald-500 transition-all">
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Price of Inventory Item</label>
                            <div class="flex items-center">
                                <span class="text-xl text-gray-400 mr-1">$</span>
                                <input type="number"
                                    class="w-full border-none p-0 text-xl font-medium text-gray-900 dark:text-white bg-transparent focus:ring-0 placeholder-gray-300"
                                    value="<?php echo number_format($loan_default_amount, 0, '', ''); ?>" id="lc-amount">
                            </div>
                        </div>
                    </div>

                    <!-- Sales Tax (%) -->
                    <div>
                        <div
                            class="border border-gray-200 dark:border-zinc-700 px-4 py-3 bg-white dark:bg-zinc-900 focus-within:border-emerald-500 dark:focus-within:border-emerald-500 focus-within:ring-1 focus-within:ring-emerald-500 transition-all">
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Sales Tax</label>
                            <div class="flex items-center">
                                <input type="number"
                                    class="w-full border-none p-0 text-xl font-medium text-gray-900 dark:text-white bg-transparent focus:ring-0 placeholder-gray-300"
                                    value="6.25" step="0.01" id="lc-tax">
                                <span class="text-xl text-gray-400 ml-1">%</span>
                            </div>
                        </div>
                    </div>

                    <!-- Trade-In Value or Down Payment -->
                    <div>
                        <div
                            class="border border-gray-200 dark:border-zinc-700  px-4 py-3 bg-white dark:bg-zinc-900 focus-within:border-emerald-500 dark:focus-within:border-emerald-500 focus-within:ring-1 focus-within:ring-emerald-500 transition-all">
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Down Payment</label>
                            <div class="flex items-center">
                                <span class="text-xl text-gray-400 mr-1">$</span>
                                <input type="number"
                                    class="w-full border-none p-0 text-xl font-medium text-gray-900 dark:text-white bg-transparent focus:ring-0 placeholder-gray-300"
                                    value="0" id="lc-down">
                            </div>
                        </div>
                    </div>

                    <!-- Interest Rate -->
                    <div>
                        <div
                            class="border border-gray-200 dark:border-zinc-700 px-4 py-3 bg-white dark:bg-zinc-900 h-full focus-within:border-emerald-500 dark:focus-within:border-emerald-500 focus-within:ring-1 focus-within:ring-emerald-500 transition-all">
                            <label
                                class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Interest
                                Rate</label>
                            <div class="flex items-center">
                                <input type="number"
                                    class="w-full border-none p-0 text-xl font-medium text-gray-900 dark:text-white bg-transparent focus:ring-0 placeholder-gray-300"
                                    value="7.25" step="0.1" id="lc-rate">
                                <span class="text-xl text-gray-400 ml-1">%</span>
                            </div>
                        </div>
                    </div>

                    <!-- Term (Months) -->
                    <div class="md:col-span-2">
                        <div
                            class="border border-gray-200 dark:border-zinc-700 px-4 py-3 bg-white dark:bg-zinc-900 transition-all">
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Loan Term (Months)</label>
                            <div class="flex items-center">
                                <input type="number"
                                    class="w-full border-none p-0 text-xl font-medium text-gray-900 dark:text-white bg-transparent !focus:ring-0 placeholder-gray-300"
                                    value="144" id="lc-term">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Calculator Results -->
            <div>
                <div
                    class="card border border-gray-200 dark:border-zinc-700  p-8 h-full bg-white dark:bg-zinc-900">
                    <div class="mb-8 text-center">
                        <h5
                            class="font-medium text-gray-500 dark:text-gray-400 mb-2 uppercase tracking-wide text-sm">
                            Estimated Monthly Payment</h5>
                        <div class="text-4xl md:text-5xl font-bold text-emerald-600 dark:text-emerald-400 tracking-tight"
                            id="lc-monthly-payment">--</div>
                    </div>

                    <!-- 2 Column Layout: Summary Left, Chart Right -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <!-- Left: Summary List -->
                        <div class="space-y-4">
                            <!-- Price of Inventory Item -->
                            <div class="flex justify-between items-center py-3 border-b border-gray-100 dark:border-zinc-800">
                                <span class="text-base font-medium text-gray-700 dark:text-gray-300">Price:</span>
                                <span class="text-base font-bold text-gray-900 dark:text-white" id="lc-summary-price">$ 0</span>
                            </div>

                            <!-- Sales Tax (%) -->
                            <div class="flex justify-between items-center py-3 border-b border-gray-100 dark:border-zinc-800">
                                <span class="text-base font-medium text-gray-700 dark:text-gray-300">Sales Tax:</span>
                                <span class="text-base font-bold text-gray-900 dark:text-white" id="lc-summary-tax">0 %</span>
                            </div>

                            <!-- Down Payment -->
                            <div class="flex justify-between items-center py-3 border-b border-gray-100 dark:border-zinc-800">
                                <span class="text-base font-medium text-gray-700 dark:text-gray-300">Down Payment:</span>
                                <span class="text-base font-bold text-gray-900 dark:text-white" id="lc-summary-down">$ 0</span>
                            </div>

                            <!-- Rate (APR %) -->
                            <div class="flex justify-between items-center py-3 border-b border-gray-100 dark:border-zinc-800">
                                <span class="text-base font-medium text-gray-700 dark:text-gray-300">Rate (APR):</span>
                                <span class="text-base font-bold text-gray-900 dark:text-white" id="lc-summary-rate">0 %</span>
                            </div>

                            <!-- Term Months -->
                            <div class="flex justify-between items-center py-3 border-b border-gray-100 dark:border-zinc-800">
                                <span class="text-base font-medium text-gray-700 dark:text-gray-300">Term:</span>
                                <span class="text-base font-bold text-gray-900 dark:text-white" id="lc-summary-term">0 Months</span>
                            </div>
                        </div>

                        <!-- Right: Chart Visualization -->
                        <div class="flex items-center justify-center">
                            <div class="h-[250px] w-[250px] relative">
                                <canvas id="lc-chart"></canvas>
                                <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 text-center pointer-events-none">
                                    <div class="font-bold text-2xl text-gray-900 dark:text-white" id="lc-chart-text-val">--</div>
                                    <div class="text-xs text-gray-400 leading-tight mt-1">Est. Monthly<br>Payment</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
