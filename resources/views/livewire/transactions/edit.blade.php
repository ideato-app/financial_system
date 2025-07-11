<!-- Edit Transaction Page with Modern Light Theme -->
<div class="min-h-screen bg-gradient-to-br from-indigo-50 via-purple-50 to-pink-50">
    <!-- Header Section -->
    <div class="bg-white/70 backdrop-blur-sm border-b border-indigo-200/20 shadow-sm">
        <div class="p-6">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl shadow-lg">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                </div>
                <div>
                    <h3
                        class="text-2xl font-bold bg-gradient-to-r from-indigo-600 to-purple-600 bg-clip-text text-transparent">
                        تعديل المعاملة</h3>
                    <p class="text-gray-600 text-sm">تعديل بيانات المعاملة المالية</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Form Container -->
    <div class="p-6">
        <div class="max-w-4xl mx-auto">
            <div class="bg-white/60 backdrop-blur-sm rounded-2xl shadow-xl border border-white/20 overflow-hidden">
                <div class="p-8 space-y-8">
                    <form wire:submit.prevent="updateTransaction">
                        <!-- Customer Information Section -->
                        <div class="bg-white/50 backdrop-blur-sm rounded-xl p-6 border border-white/20 shadow-lg">
                            <div class="flex items-center gap-3 mb-6">
                                <div class="p-2 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-lg">
                                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-900">معلومات العميل</h3>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Customer Name -->
                                <div>
                                    <label for="customerName" class="block text-sm font-medium text-gray-700 mb-2">اسم
                                        العميل</label>
                                    <input wire:model="customerName" id="customerName" name="customerName"
                                        type="text"
                                        class="w-full px-4 py-3 bg-white/70 border border-gray-200/50 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all duration-200 text-gray-900 placeholder-gray-500"
                                        required />
                                    <x-input-error class="mt-2" :messages="$errors->get('customerName')" />
                                </div>

                                <!-- Customer Mobile Number -->
                                <div>
                                    <label for="customerMobileNumber"
                                        class="block text-sm font-medium text-gray-700 mb-2">رقم هاتف العميل</label>
                                    <input wire:model="customerMobileNumber" id="customerMobileNumber"
                                        name="customerMobileNumber" type="text"
                                        class="w-full px-4 py-3 bg-white/70 border border-gray-200/50 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all duration-200 text-gray-900 placeholder-gray-500"
                                        required />
                                    <x-input-error class="mt-2" :messages="$errors->get('customerMobileNumber')" />
                                </div>

                                <!-- Line Mobile Number field removed as it doesn't exist in the database -->

                                <!-- Customer Code -->
                                <div>
                                    <label for="customerCode" class="block text-sm font-medium text-gray-700 mb-2">كود
                                        العميل (اختياري)</label>
                                    <input wire:model="customerCode" id="customerCode" name="customerCode"
                                        type="text"
                                        class="w-full px-4 py-3 bg-white/70 border border-gray-200/50 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all duration-200 text-gray-900 placeholder-gray-500" />
                                    <x-input-error class="mt-2" :messages="$errors->get('customerCode')" />
                                </div>
                            </div>
                        </div>

                        <!-- Transaction Details Section -->
                        <div class="bg-white/50 backdrop-blur-sm rounded-xl p-6 border border-white/20 shadow-lg">
                            <div class="flex items-center gap-3 mb-6">
                                <div class="p-2 bg-gradient-to-br from-emerald-500 to-green-600 rounded-lg">
                                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1" />
                                    </svg>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-900">تفاصيل المعاملة</h3>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                <!-- Amount -->
                                <div>
                                    <label for="amount" class="block text-sm font-medium text-gray-700 mb-2">المبلغ
                                        (جنيه مصري)</label>
                                    <input wire:model="amount" id="amount" name="amount" type="number"
                                        step="0.01"
                                        class="w-full px-4 py-3 bg-white/70 border border-gray-200/50 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-all duration-200 text-gray-900 placeholder-gray-500"
                                        required />
                                    <x-input-error class="mt-2" :messages="$errors->get('amount')" />
                                </div>

                                <!-- Commission -->
                                <div>
                                    <label for="commission" class="block text-sm font-medium text-gray-700 mb-2">العمولة
                                        (جنيه مصري)</label>
                                    <input wire:model="commission" id="commission" name="commission" type="number"
                                        step="0.01"
                                        class="w-full px-4 py-3 bg-gray-100/70 border border-gray-200/50 rounded-xl text-gray-900 cursor-not-allowed"
                                        readonly />
                                    <x-input-error class="mt-2" :messages="$errors->get('commission')" />
                                </div>

                                <!-- Deduction -->
                                <div>
                                    <label for="deduction" class="block text-sm font-medium text-gray-700 mb-2">الخصم
                                        (تعديل يدوي من العمولة)</label>
                                    <input wire:model="deduction" id="deduction" name="deduction" type="number"
                                        step="0.01"
                                        class="w-full px-4 py-3 bg-white/70 border border-gray-200/50 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-all duration-200 text-gray-900 placeholder-gray-500" />
                                    <x-input-error class="mt-2" :messages="$errors->get('deduction')" />
                                </div>

                                <!-- Transaction Type -->
                                <div>
                                    <label for="transactionType"
                                        class="block text-sm font-medium text-gray-700 mb-2">نوع المعاملة</label>
                                    <select wire:model="transactionType" id="transactionType" name="transactionType"
                                        class="w-full px-4 py-3 bg-white/70 border border-gray-200/50 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-all duration-200 text-gray-900"
                                        required>
                                        <option value="Transfer">تحويل</option>
                                        <option value="Withdrawal">سحب</option>
                                        <option value="Deposit">إيداع</option>
                                        <option value="Adjustment">تعديل</option>
                                    </select>
                                    <x-input-error class="mt-2" :messages="$errors->get('transactionType')" />
                                </div>

                                <!-- Agent Name -->
                                <div>
                                    <label for="agentName" class="block text-sm font-medium text-gray-700 mb-2">اسم
                                        الوكيل</label>
                                    <input wire:model="agentName" id="agentName" name="agentName" type="text"
                                        class="w-full px-4 py-3 bg-gray-100/70 border border-gray-200/50 rounded-xl text-gray-900 cursor-not-allowed"
                                        readonly />
                                    <x-input-error class="mt-2" :messages="$errors->get('agentName')" />
                                </div>

                                <!-- Status -->
                                <div>
                                    <label for="status"
                                        class="block text-sm font-medium text-gray-700 mb-2">الحالة</label>
                                    <select wire:model="status" id="status" name="status"
                                        class="w-full px-4 py-3 bg-white/70 border border-gray-200/50 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-all duration-200 text-gray-900"
                                        required>
                                        <option value="Completed">مكتملة</option>
                                        <option value="Pending">معلقة</option>
                                        <option value="Rejected">مرفوضة</option>
                                    </select>
                                    <x-input-error class="mt-2" :messages="$errors->get('status')" />
                                </div>
                            </div>
                        </div>

                        <!-- Location & Configuration Section -->
                        <div class="bg-white/50 backdrop-blur-sm rounded-xl p-6 border border-white/20 shadow-lg">
                            <div class="flex items-center gap-3 mb-6">
                                <div class="p-2 bg-gradient-to-br from-purple-500 to-pink-600 rounded-lg">
                                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                    </svg>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-900">الموقع والإعدادات</h3>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <!-- Branch -->
                                <div>
                                    <label for="branchId"
                                        class="block text-sm font-medium text-gray-700 mb-2">الفرع</label>
                                    <select wire:model="branchId" id="branchId" name="branchId"
                                        class="w-full px-4 py-3 bg-white/70 border border-gray-200/50 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all duration-200 text-gray-900"
                                        required>
                                        <option value="">اختر الفرع</option>
                                        @foreach ($branches as $branch)
                                            <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                        @endforeach
                                    </select>
                                    <x-input-error class="mt-2" :messages="$errors->get('branchId')" />
                                </div>

                                <!-- Line -->
                                <div>
                                    <label for="lineId" class="block text-sm font-medium text-gray-700 mb-2">الخط
                                        المستخدم</label>
                                    <select wire:model="lineId" id="lineId" name="lineId"
                                        class="w-full px-4 py-3 bg-white/70 border border-gray-200/50 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all duration-200 text-gray-900"
                                        required>
                                        <option value="">اختر الخط</option>
                                        @foreach ($lines as $line)
                                            <option value="{{ $line->id }}">{{ $line->mobile_number }} (الرصيد:
                                                {{ $line->current_balance }} ج.م)</option>
                                        @endforeach
                                    </select>
                                    <x-input-error class="mt-2" :messages="$errors->get('lineId')" />
                                </div>

                                <!-- Safe -->
                                <div>
                                    <label for="safeId" class="block text-sm font-medium text-gray-700 mb-2">الخزنة
                                        المستخدمة</label>
                                    <select wire:model="safeId" id="safeId" name="safeId"
                                        class="w-full px-4 py-3 bg-white/70 border border-gray-200/50 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all duration-200 text-gray-900"
                                        required>
                                        <option value="">اختر الخزنة</option>
                                        @foreach ($safes as $safe)
                                            <option value="{{ $safe->id }}">{{ $safe->name }} (الرصيد:
                                                {{ $safe->current_balance }} ج.م)</option>
                                        @endforeach
                                    </select>
                                    <x-input-error class="mt-2" :messages="$errors->get('safeId')" />
                                </div>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="flex justify-end pt-6 border-t border-gray-200/30">
                            <button type="submit"
                                class="px-8 py-3 bg-gradient-to-r from-indigo-500 to-purple-600 text-white font-semibold rounded-xl shadow-lg hover:from-indigo-600 hover:to-purple-700 transform hover:scale-105 transition-all duration-200">
                                <div class="flex items-center gap-2">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M5 13l4 4L19 7" />
                                    </svg>
                                    تحديث المعاملة
                                </div>
                            </button>
                        </div>

                        <!-- Messages Section -->
                        @if (session('message') || session('error'))
                            <div class="space-y-4">
                                @if (session('message'))
                                    <div x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 4000)"
                                        class="p-4 bg-green-50/70 border border-green-200/50 rounded-xl backdrop-blur-sm">
                                        <div class="flex items-center gap-3">
                                            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M5 13l4 4L19 7" />
                                            </svg>
                                            <span class="text-green-800 font-medium">{{ session('message') }}</span>
                                        </div>
                                    </div>
                                @endif

                                @if (session('error'))
                                    <div x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 4000)"
                                        class="p-4 bg-red-50/70 border border-red-200/50 rounded-xl backdrop-blur-sm">
                                        <div class="flex items-center gap-3">
                                            <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                            <span class="text-red-800 font-medium">{{ session('error') }}</span>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
