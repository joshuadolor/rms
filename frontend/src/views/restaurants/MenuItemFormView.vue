<template>
  <div class="max-w-3xl" data-testid="menu-item-form">
    <div v-if="loading && isEdit" class="space-y-4">
      <div class="h-32 rounded-xl bg-slate-200 dark:bg-slate-700 animate-pulse" />
      <div class="h-48 rounded-xl bg-slate-200 dark:bg-slate-700 animate-pulse" />
    </div>

    <div
      v-else-if="isEdit && !loading && !restaurant && !standaloneItem"
      class="bg-white dark:bg-zinc-900 rounded-xl border border-slate-200 dark:border-slate-800 p-8 text-center"
    >
      <p class="text-slate-500 dark:text-slate-400 mb-4">Restaurant or menu item not found.</p>
      <AppBackLink :to="backLink" />
    </div>

    <template v-else>
      <header class="mb-6 lg:mb-8" data-testid="form-header">
        <h2 class="text-xl font-bold text-charcoal dark:text-white lg:text-2xl">
          {{ isEdit ? 'Edit menu item' : 'Add menu item' }}
        </h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
          {{ isEdit ? 'Name and description per language. At least the default language is required.' : 'Add the name and optional description. You can add other languages after creating the item.' }}
        </p>
      </header>

      <form v-if="restaurant || standaloneItem" class="space-y-6 lg:space-y-8" novalidate data-testid="menu-item-form-form" @submit.prevent="handleSubmit">
        <div
          id="menu-item-form-error"
          role="alert"
          aria-live="polite"
          data-testid="form-error"
          class="p-3 rounded-lg bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300 text-sm"
          :class="{ 'sr-only': !error }"
        >
          {{ error }}
        </div>

        <!-- Catalog item summary: type, combo entries or variant SKUs (read-only) -->
        <section
          v-if="standaloneItem && isMenuItemsModule && (standaloneItem.type === 'combo' || standaloneItem.type === 'with_variants')"
          class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-zinc-900/80 p-4 lg:p-6 space-y-4"
        >
          <h3 class="font-semibold text-charcoal dark:text-white flex items-center gap-2">
            <span class="material-icons text-slate-500 dark:text-slate-400">info</span>
            Type: {{ standaloneItem.type === 'combo' ? 'Combo' : 'With variants' }}
          </h3>
          <template v-if="standaloneItem.type === 'combo'">
            <p class="text-sm text-slate-600 dark:text-slate-400">Entries (edit below to change):</p>
            <ul class="space-y-2">
              <li
                v-for="(entry, idx) in (standaloneItem.combo_entries || [])"
                :key="idx"
                class="flex flex-wrap items-baseline gap-2 text-sm"
              >
                <span class="font-medium text-charcoal dark:text-white">{{ comboEntryName(entry) }}</span>
                <span v-if="comboEntryVariantLabel(entry)" class="text-slate-500 dark:text-slate-400">({{ comboEntryVariantLabel(entry) }})</span>
                <span class="text-slate-600 dark:text-slate-300">× {{ entry.quantity }}</span>
                <span v-if="entry.modifier_label" class="text-slate-500 dark:text-slate-400">— {{ entry.modifier_label }}</span>
              </li>
            </ul>
            <p v-if="standaloneItem.combo_price != null" class="text-sm font-medium text-slate-700 dark:text-slate-300">
              Combo price: {{ formatBasePrice(standaloneItem.combo_price) }}
            </p>
          </template>
          <template v-else-if="standaloneItem.type === 'with_variants'">
            <p class="text-sm text-slate-600 dark:text-slate-400">Option groups:</p>
            <ul class="list-disc list-inside text-sm text-charcoal dark:text-white mb-4">
              <li v-for="grp in (standaloneItem.variant_option_groups || [])" :key="grp.name">
                {{ grp.name }}: {{ (grp.values || []).join(', ') }}
              </li>
            </ul>
            <p class="text-sm text-slate-600 dark:text-slate-400 mb-2">Variants (price{{ standaloneItem.variant_skus?.some(s => s.image_url) ? ', image' : '' }}):</p>
            <ul class="space-y-2">
              <li
                v-for="sku in (standaloneItem.variant_skus || [])"
                :key="sku.uuid"
                class="flex flex-wrap items-center gap-2 text-sm"
              >
                <span class="font-medium text-charcoal dark:text-white">{{ variantSkuLabel(sku) }}</span>
                <span class="text-slate-600 dark:text-slate-300">{{ formatBasePrice(sku.price) }}</span>
                <img v-if="sku.image_url" :src="sku.image_url" alt="" class="w-10 h-10 object-cover rounded" />
              </li>
            </ul>
          </template>
        </section>

        <!-- Type selector (catalog standalone only) -->
        <section
          v-if="standaloneItem && isMenuItemsModule"
          class="bg-white dark:bg-zinc-900 rounded-xl border border-slate-200 dark:border-slate-800 p-4 lg:p-6"
        >
          <h3 class="font-semibold text-charcoal dark:text-white mb-3">Type</h3>
          <div class="flex flex-col gap-2" role="radiogroup" aria-label="Menu item type">
            <label class="flex items-center gap-3 min-h-[44px] cursor-pointer rounded-lg border border-slate-200 dark:border-slate-700 p-3 has-[:checked]:ring-2 has-[:checked]:ring-primary has-[:checked]:border-primary">
              <input v-model="form.type" type="radio" value="simple" class="w-5 h-5" data-testid="type-simple" />
              <span class="font-medium text-charcoal dark:text-white">Simple</span>
              <span class="text-sm text-slate-500 dark:text-slate-400">Single item with one price</span>
            </label>
            <label class="flex items-center gap-3 min-h-[44px] cursor-pointer rounded-lg border border-slate-200 dark:border-slate-700 p-3 has-[:checked]:ring-2 has-[:checked]:ring-primary has-[:checked]:border-primary">
              <input v-model="form.type" type="radio" value="combo" class="w-5 h-5" data-testid="type-combo" />
              <span class="font-medium text-charcoal dark:text-white">Combo</span>
              <span class="text-sm text-slate-500 dark:text-slate-400">Bundle of other menu items</span>
            </label>
            <label class="flex items-center gap-3 min-h-[44px] cursor-pointer rounded-lg border border-slate-200 dark:border-slate-700 p-3 has-[:checked]:ring-2 has-[:checked]:ring-primary has-[:checked]:border-primary">
              <input v-model="form.type" type="radio" value="with_variants" class="w-5 h-5" data-testid="type-with_variants" />
              <span class="font-medium text-charcoal dark:text-white">With variants</span>
              <span class="text-sm text-slate-500 dark:text-slate-400">Options (e.g. size, type) with price per combination</span>
            </label>
          </div>
        </section>

        <!-- Name & description (translations): before Price -->
        <template v-if="restaurant || standaloneItem">
          <section
            class="bg-white dark:bg-zinc-900 rounded-xl border border-slate-200 dark:border-slate-800 p-4 lg:p-6 space-y-4"
            :data-testid="`form-section-locale-${selectedLocale}`"
          >
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
              <label for="menu-item-locale-select" class="block text-sm font-semibold text-charcoal dark:text-white">
                Language
              </label>
              <select
                id="menu-item-locale-select"
                v-model="selectedLocale"
                class="min-h-[44px] w-full sm:w-auto min-w-[12rem] rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-zinc-800 text-charcoal dark:text-white px-4 py-2 text-sm font-medium focus:ring-2 focus:ring-primary focus:outline-none"
                aria-label="Select language for name and description"
                data-testid="menu-item-locale-select"
              >
                <option
                  v-for="loc in formLocales"
                  :key="loc"
                  :value="loc"
                >
                  {{ getLocaleDisplay(loc) }}
                </option>
              </select>
            </div>
            <template v-if="selectedLocale && form.translations[selectedLocale]">
              <div class="flex flex-wrap items-center justify-between gap-2 pt-2">
                <h3 class="font-semibold text-charcoal dark:text-white flex items-center gap-2">
                  <span class="material-icons text-slate-500 dark:text-slate-400">translate</span>
                  {{ getLocaleDisplay(selectedLocale) }}
                </h3>
                <AppButton
                  v-if="restaurant && selectedLocale !== restaurant.default_locale"
                  type="button"
                  variant="secondary"
                  size="sm"
                  class="min-h-[44px]"
                  :disabled="!!translatingLocale"
                  :aria-busy="translatingLocale === selectedLocale"
                  aria-label="Translate from default language"
                  data-testid="menu-item-translate-from-default"
                  @click="translateLocale(selectedLocale)"
                >
                  <template #icon>
                    <span
                      v-if="translatingLocale === selectedLocale"
                      class="material-icons animate-spin text-lg"
                      aria-hidden="true"
                    >sync</span>
                    <span
                      v-else
                      class="material-icons text-lg"
                      aria-hidden="true"
                    >translate</span>
                  </template>
                  {{ translatingLocale === selectedLocale ? 'Translating…' : 'Translate from default' }}
                </AppButton>
              </div>
              <AppInput
                v-model="form.translations[selectedLocale].name"
                :label="`Name (${getLocaleDisplay(selectedLocale)})`"
                type="text"
                :placeholder="(restaurant?.default_locale ?? 'en') === selectedLocale ? 'e.g. Margherita Pizza' : ''"
                :error="fieldErrors[`translations.${selectedLocale}.name`]"
              />
              <div>
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1" :for="`menu-item-desc-${selectedLocale}`">Description (optional)</label>
                <textarea
                  :id="`menu-item-desc-${selectedLocale}`"
                  v-model="form.translations[selectedLocale].description"
                  rows="3"
                  class="w-full rounded-lg ring-1 ring-gray-200 dark:ring-zinc-700 focus:ring-2 focus:ring-primary transition-all bg-white dark:bg-zinc-800 border-0 py-3 px-4 text-charcoal dark:text-white resize-none min-h-[44px]"
                  :placeholder="`Description in ${getLocaleDisplay(selectedLocale)}`"
                />
              </div>
            </template>
          </section>
        </template>

        <!-- Price: hide for catalog with_variants (price per SKU); show for restaurant or catalog simple/combo -->
        <section
          v-if="restaurant || (standaloneItem && form.type !== 'with_variants')"
          class="bg-white dark:bg-zinc-900 rounded-xl border border-slate-200 dark:border-slate-800 p-4 lg:p-6 space-y-4"
        >
          <h3 class="font-semibold text-charcoal dark:text-white flex items-center gap-2">
            <span class="material-icons text-slate-500 dark:text-slate-400">payments</span>
            {{ standaloneItem && form.type === 'combo' ? 'Combo price (optional)' : 'Price' }}
          </h3>
          <template v-if="itemFromCatalog">
            <AppInput
              v-model="form.price_override"
              :label="`Override price (leave empty to use base: ${formatBasePrice(basePrice)})`"
              type="number"
              min="0"
              step="0.01"
              placeholder="Same as base"
              :error="fieldErrors.price_override"
            />
          </template>
          <template v-else-if="standaloneItem && form.type === 'combo'">
            <AppInput
              v-model="form.combo_price"
              label="Combo price (optional)"
              type="number"
              min="0"
              step="0.01"
              placeholder="e.g. 12.00"
              :error="fieldErrors.combo_price"
            />
          </template>
          <AppInput
            v-else
            v-model="form.price"
            label="Price (optional)"
            type="number"
            min="0"
            step="0.01"
            placeholder="e.g. 10.00"
            :error="fieldErrors.price"
          />
          <AppButton
            v-if="itemFromCatalog && hasOverrides"
            type="button"
            variant="secondary"
            size="sm"
            class="min-h-[44px]"
            :disabled="saving || reverting"
            data-testid="revert-to-base"
            @click="revertToBase"
          >
            <template v-if="reverting" #icon>
              <span class="material-icons animate-spin">sync</span>
            </template>
            {{ reverting ? 'Reverting…' : 'Revert to base value' }}
          </AppButton>
        </section>

        <!-- Image (menu items / catalog context only): simple/combo one image; with_variants one per SKU -->
        <section
          v-if="isMenuItemsModule && isEdit && standaloneItem && (effectiveItemType === 'simple' || effectiveItemType === 'combo' || effectiveItemType === 'with_variants')"
          class="bg-white dark:bg-zinc-900 rounded-xl border border-slate-200 dark:border-slate-800 p-4 lg:p-6 space-y-4"
          data-testid="menu-item-image-section"
        >
          <h3 class="font-semibold text-charcoal dark:text-white flex items-center gap-2">
            <span class="material-icons text-slate-500 dark:text-slate-400">image</span>
            Image
          </h3>
          <p class="text-sm text-slate-500 dark:text-slate-400">
            Optional. JPEG, PNG, GIF or WebP, max 2MB. Stored as 512×512 square.
          </p>

          <!-- Simple or combo: single item image -->
          <template v-if="effectiveItemType === 'simple' || effectiveItemType === 'combo'">
            <div class="flex flex-wrap items-start gap-4">
              <div
                v-if="menuItemFromApi?.image_url"
                class="relative shrink-0"
              >
                <img
                  :src="menuItemFromApi.image_url"
                  alt=""
                  class="w-24 h-24 object-cover rounded-lg border border-slate-200 dark:border-slate-700"
                  data-testid="menu-item-image-preview"
                />
                <button
                  type="button"
                  class="absolute -top-2 -right-2 w-8 h-8 rounded-full bg-red-500 text-white flex items-center justify-center shadow min-h-[44px] min-w-[44px]"
                  aria-label="Remove image"
                  :disabled="uploadingImageFor === 'item'"
                  data-testid="menu-item-image-remove"
                  @click="removeItemImage"
                >
                  <span class="material-icons text-lg">close</span>
                </button>
              </div>
              <div class="flex flex-col gap-2">
                <input
                  ref="itemImageFileInputRef"
                  type="file"
                  accept="image/jpeg,image/png,image/gif,image/webp"
                  class="sr-only"
                  data-testid="menu-item-image-input"
                  @change="onItemImageSelect"
                />
                <AppButton
                  type="button"
                  variant="secondary"
                  size="sm"
                  class="min-h-[44px]"
                  :disabled="uploadingImageFor === 'item'"
                  @click="itemImageFileInputRef?.click()"
                >
                  <template v-if="uploadingImageFor === 'item'" #icon>
                    <span class="material-icons animate-spin">sync</span>
                  </template>
                  <template v-else #icon>
                    <span class="material-icons">upload</span>
                  </template>
                  {{ menuItemFromApi?.image_url ? 'Change image' : 'Upload image' }}
                </AppButton>
                <AppButton
                  v-require-paid
                  type="button"
                  variant="ghost"
                  size="sm"
                  class="min-h-[44px] text-primary"
                  data-testid="menu-item-beautify-ai"
                  @click="onBeautifyAiClick"
                >
                  <template #icon><span class="material-icons">auto_awesome</span></template>
                  Beautify with AI
                </AppButton>
              </div>
            </div>
          </template>

          <!-- With variants: image per SKU -->
          <template v-else-if="effectiveItemType === 'with_variants' && menuItemFromApi?.variant_skus?.length">
            <ul class="space-y-3">
              <li
                v-for="sku in menuItemFromApi.variant_skus"
                :key="sku.uuid"
                class="flex flex-wrap items-center gap-3 p-3 rounded-xl border border-slate-200 dark:border-slate-700"
              >
                <span class="font-medium text-charcoal dark:text-white min-w-[8rem]">{{ variantSkuLabel(sku) }}</span>
                <span class="text-slate-600 dark:text-slate-300">{{ formatBasePrice(sku.price) }}</span>
                <div class="flex items-center gap-2">
                  <img
                    v-if="sku.image_url"
                    :src="sku.image_url"
                    alt=""
                    class="w-12 h-12 object-cover rounded border border-slate-200 dark:border-slate-700"
                  />
                  <input
                    :ref="(el) => setVariantImageInputRef(sku.uuid, el)"
                    type="file"
                    accept="image/jpeg,image/png,image/gif,image/webp"
                    class="sr-only"
                    :data-testid="`variant-image-input-${sku.uuid}`"
                    @change="(e) => onVariantImageSelect(sku.uuid, e)"
                  />
                  <AppButton
                    type="button"
                    variant="secondary"
                    size="sm"
                    class="min-h-[44px]"
                    :disabled="uploadingImageFor === sku.uuid"
                    @click="variantImageInputRefs[sku.uuid]?.click()"
                  >
                    <template v-if="uploadingImageFor === sku.uuid" #icon>
                      <span class="material-icons animate-spin">sync</span>
                    </template>
                    <template v-else #icon><span class="material-icons">upload</span></template>
                    {{ sku.image_url ? 'Change' : 'Add' }}
                  </AppButton>
                  <AppButton
                    v-if="sku.image_url"
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="min-h-[44px] min-w-[44px] text-red-600 dark:text-red-400"
                    aria-label="Remove variant image"
                    :disabled="uploadingImageFor === sku.uuid"
                    @click="removeVariantImage(sku.uuid)"
                  >
                    <span class="material-icons">close</span>
                  </AppButton>
                </div>
              </li>
            </ul>
          </template>
        </section>

        <!-- Catalog combo entries (standalone edit) -->
        <section
          v-if="standaloneItem && isMenuItemsModule && form.type === 'combo'"
          class="bg-white dark:bg-zinc-900 rounded-xl border border-slate-200 dark:border-slate-800 p-4 lg:p-6 space-y-4"
        >
          <div class="flex items-center justify-between gap-2">
            <h3 class="font-semibold text-charcoal dark:text-white">Combo entries</h3>
            <AppButton type="button" variant="secondary" size="sm" class="min-h-[44px]" @click="addComboEntry">
              <template #icon><span class="material-icons">add</span></template>
              Add entry
            </AppButton>
          </div>
          <p v-if="fieldErrors.combo_entries" class="text-sm text-red-600 dark:text-red-400">{{ fieldErrors.combo_entries }}</p>
          <ul class="space-y-4">
            <li
              v-for="(entry, idx) in form.combo_entries"
              :key="idx"
              class="flex flex-col gap-3 p-4 rounded-xl border border-slate-200 dark:border-slate-700"
            >
              <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-slate-600 dark:text-slate-400">Entry {{ idx + 1 }}</span>
                <AppButton type="button" variant="ghost" size="sm" class="min-h-[44px] min-w-[44px] text-red-600 dark:text-red-400" aria-label="Remove entry" @click="removeComboEntry(idx)">
                  <span class="material-icons">remove_circle_outline</span>
                </AppButton>
              </div>
              <div class="grid gap-3 sm:grid-cols-2">
                <div>
                  <label :for="`edit-combo-item-${idx}`" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">Menu item</label>
                  <select
                    :id="`edit-combo-item-${idx}`"
                    v-model="entry.menu_item_uuid"
                    class="w-full min-h-[44px] rounded-lg border px-4 py-2 bg-white dark:bg-zinc-800 text-charcoal dark:text-white transition-colors"
                    :class="fieldErrors[`combo_entries.${idx}.menu_item_uuid`] ? 'border-red-500 dark:border-red-400 ring-2 ring-red-500/20' : 'border-slate-200 dark:border-slate-700'"
                    :aria-invalid="!!fieldErrors[`combo_entries.${idx}.menu_item_uuid`]"
                    :aria-describedby="fieldErrors[`combo_entries.${idx}.menu_item_uuid`] ? `edit-combo-item-${idx}-error` : undefined"
                  >
                    <option value="">— Select item —</option>
                    <option v-for="catItem in catalogItemsForSummary" :key="catItem.uuid" :value="catItem.uuid">
                      {{ catalogEditComboItemName(catItem) }}
                    </option>
                  </select>
                  <p
                    v-if="fieldErrors[`combo_entries.${idx}.menu_item_uuid`]"
                    :id="`edit-combo-item-${idx}-error`"
                    class="text-sm text-red-600 dark:text-red-400 mt-1"
                    role="alert"
                  >
                    {{ fieldErrors[`combo_entries.${idx}.menu_item_uuid`] }}
                  </p>
                </div>
                <div v-if="catalogEditItemHasVariants(entry.menu_item_uuid)">
                  <label :for="`edit-combo-variant-${idx}`" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">Variant</label>
                  <select
                    :id="`edit-combo-variant-${idx}`"
                    v-model="entry.variant_uuid"
                    class="w-full min-h-[44px] rounded-lg border px-4 py-2 bg-white dark:bg-zinc-800 text-charcoal dark:text-white transition-colors"
                    :class="fieldErrors[`combo_entries.${idx}.variant_uuid`] ? 'border-red-500 dark:border-red-400 ring-2 ring-red-500/20' : 'border-slate-200 dark:border-slate-700'"
                    :aria-invalid="!!fieldErrors[`combo_entries.${idx}.variant_uuid`]"
                    :aria-describedby="fieldErrors[`combo_entries.${idx}.variant_uuid`] ? `edit-combo-variant-${idx}-error` : undefined"
                  >
                    <option :value="null">— Select variant —</option>
                    <option v-for="sku in catalogEditVariantSkus(entry.menu_item_uuid)" :key="sku.uuid" :value="sku.uuid">
                      {{ sku.displayLabel ? sku.displayLabel() : Object.values(sku.option_values || {}).join(', ') }}
                    </option>
                  </select>
                  <p
                    v-if="fieldErrors[`combo_entries.${idx}.variant_uuid`]"
                    :id="`edit-combo-variant-${idx}-error`"
                    class="text-sm text-red-600 dark:text-red-400 mt-1"
                    role="alert"
                  >
                    {{ fieldErrors[`combo_entries.${idx}.variant_uuid`] }}
                  </p>
                </div>
              </div>
              <div class="grid gap-3 sm:grid-cols-2">
                <AppInput v-model.number="entry.quantity" label="Quantity" type="number" min="1" :error="fieldErrors[`combo_entries.${idx}.quantity`]" />
                <AppInput v-model="entry.modifier_label" label="Modifier (optional)" type="text" placeholder="e.g. No ice" />
              </div>
            </li>
          </ul>
        </section>

        <!-- Catalog with variants (standalone edit) -->
        <template v-if="standaloneItem && isMenuItemsModule && form.type === 'with_variants'">
          <section class="bg-white dark:bg-zinc-900 rounded-xl border border-slate-200 dark:border-slate-800 p-4 lg:p-6 space-y-4">
            <div class="flex items-center justify-between gap-2">
              <h3 class="font-semibold text-charcoal dark:text-white">Option groups</h3>
              <AppButton type="button" variant="secondary" size="sm" class="min-h-[44px]" @click="addEditOptionGroup">
                <template #icon><span class="material-icons">add</span></template>
                Add group
              </AppButton>
            </div>
            <p v-if="fieldErrors.variant_option_groups" class="text-sm text-red-600 dark:text-red-400">{{ fieldErrors.variant_option_groups }}</p>
            <ul class="space-y-4">
              <li v-for="(grp, gIdx) in form.variant_option_groups" :key="gIdx" class="p-4 rounded-xl border border-slate-200 dark:border-slate-700 space-y-3">
                <div class="flex items-center justify-between gap-2">
                  <AppInput v-model="grp.name" :label="`Group name`" type="text" placeholder="e.g. Size" />
                  <AppButton type="button" variant="ghost" size="sm" class="min-h-[44px] min-w-[44px] text-red-600 dark:text-red-400 shrink-0" aria-label="Remove group" @click="removeEditOptionGroup(gIdx)">
                    <span class="material-icons">remove_circle_outline</span>
                  </AppButton>
                </div>
                <div>
                  <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">Values (comma-separated)</label>
                  <textarea
                    v-model="grp.valuesText"
                    rows="2"
                    class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-zinc-800 py-3 px-4 text-charcoal dark:text-white min-h-[44px]"
                    placeholder="Small, Medium, Large"
                    @input="syncEditGroupValues(gIdx)"
                  />
                </div>
              </li>
            </ul>
          </section>
          <section v-if="form.variant_skus.length > 0" class="bg-white dark:bg-zinc-900 rounded-xl border border-slate-200 dark:border-slate-800 p-4 lg:p-6 overflow-x-auto">
            <h3 class="font-semibold text-charcoal dark:text-white mb-3">Variant prices</h3>
            <p v-if="fieldErrors.variant_skus" class="text-sm text-red-600 dark:text-red-400 mb-2">{{ fieldErrors.variant_skus }}</p>
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-slate-200 dark:border-slate-700">
                  <th scope="col" class="text-left py-2 px-2 font-semibold text-charcoal dark:text-white">Combination</th>
                  <th scope="col" class="text-left py-2 px-2 font-semibold text-charcoal dark:text-white">Price</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(row, rIdx) in form.variant_skus" :key="rIdx" class="border-b border-slate-100 dark:border-slate-800">
                  <td class="py-3 px-2 text-charcoal dark:text-white">{{ row.label }}</td>
                  <td class="py-3 px-2">
                    <input
                      v-model.number="row.price"
                      type="number"
                      min="0"
                      step="0.01"
                      placeholder="0.00"
                      :aria-label="`Price for ${row.label || 'variant'}`"
                      class="w-full min-w-[5rem] min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-zinc-800 px-3 py-2 text-charcoal dark:text-white"
                    />
                  </td>
                </tr>
              </tbody>
            </table>
          </section>
        </template>

        <div class="flex flex-col sm:flex-row flex-wrap items-stretch sm:items-center justify-between gap-3">
          <router-link :to="backLink" class="block w-full sm:w-auto">
            <AppButton type="button" variant="secondary" class="min-h-[44px] w-full sm:w-auto">Cancel</AppButton>
          </router-link>
          <AppButton
            type="submit"
            variant="primary"
            class="min-h-[44px] w-full sm:w-auto sm:shrink-0"
            data-testid="form-submit"
            :disabled="saving"
          >
            <template v-if="saving" #icon>
              <span class="material-icons animate-spin">sync</span>
            </template>
            {{ saving ? 'Saving…' : (isEdit ? 'Save changes' : 'Create item') }}
          </AppButton>
        </div>
      </form>

      <!-- Confirmation modal when saving edit with deletions or text updates -->
      <AppModal
        :open="showSaveConfirmModal"
        title="Save changes?"
        description="Confirm saving changes that remove variants, combo entries, or update text."
        @close="showSaveConfirmModal = false"
      >
        <p class="text-slate-600 dark:text-slate-300">
          You’ve removed variants or combo entries, or updated name or description. Do you want to save these changes?
        </p>
        <template #footer>
          <AppButton type="button" variant="secondary" class="min-h-[44px]" @click="showSaveConfirmModal = false">
            Cancel
          </AppButton>
          <AppButton
            type="button"
            variant="primary"
            class="min-h-[44px]"
            data-testid="save-confirm-submit"
            :disabled="saving"
            @click="confirmSaveAndSubmit"
          >
            <template v-if="saving" #icon>
              <span class="material-icons animate-spin">sync</span>
            </template>
            Save changes
          </AppButton>
        </template>
      </AppModal>
    </template>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppInput from '@/components/ui/AppInput.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppBackLink from '@/components/AppBackLink.vue'
import AppModal from '@/components/ui/AppModal.vue'
import { getLocaleDisplay } from '@/config/locales'
import { formatCurrency } from '@/utils/format'
import { useBreadcrumbStore } from '@/stores/breadcrumb'
import Restaurant from '@/models/Restaurant.js'
import MenuItem from '@/models/MenuItem.js'
import { restaurantService, menuItemService, menuItemTagService, localeService, getValidationErrors, normalizeApiError } from '@/services'
import { useToastStore } from '@/stores/toast'

const route = useRoute()
const router = useRouter()
const toastStore = useToastStore()
const breadcrumbStore = useBreadcrumbStore()

/** Restaurant uuid: from route params (restaurant module) or query (standalone edit). */
const uuid = computed(() => route.params.uuid || route.query.restaurant || null)
const itemUuid = computed(() => route.params.itemUuid || null)
const isEdit = computed(() => route.meta.mode === 'edit')
const isMenuItemsModule = computed(() => !!route.meta.menuItemsModule)

const backLink = computed(() => {
  if (isMenuItemsModule.value) return { name: 'MenuItems' }
  if (uuid.value) return { name: 'RestaurantMenuItems', params: { uuid: uuid.value } }
  return { name: 'MenuItems' }
})

const loading = ref(true)
const saving = ref(false)
const reverting = ref(false)
const error = ref('')
const fieldErrors = ref({})
const restaurant = ref(null)
const standaloneItem = ref(null)
const translatingLocale = ref(null)
/** Currently selected locale in the translations dropdown. */
const selectedLocale = ref('en')
/** When editing a restaurant item that comes from catalog (source_menu_item_uuid). Overrides only in restaurant context. */
const catalogSourceUuid = ref(null)
const baseTranslations = ref({})
const basePrice = ref(null)
const hasOverrides = ref(false)
/** Catalog menu items (for combo entry names / variant labels in summary). Loaded when standalone edit and type is combo. */
const catalogItemsForSummary = ref([])
/** When true, show the "Save changes?" confirmation modal (edit only, when deletions or text updates detected). */
const showSaveConfirmModal = ref(false)
/** Snapshot after loading edit form: used to detect combo/variant deletions or text changes. */
const initialEditState = ref(null)
/** Loaded menu item from API (catalog context) for image_url and variant_skus[].image_url display. */
const menuItemFromApi = ref(null)
/** Uploading state for item or variant image (catalog context). */
const uploadingImageFor = ref(null)
const itemImageFileInputRef = ref(null)
const variantImageInputRefs = ref({})

/** Item type for image section: simple, combo, or with_variants (catalog context only). */
const effectiveItemType = computed(() => form.type || 'simple')

const form = reactive({
  sort_order: 0,
  category_uuid: null,
  price: '',
  price_override: '',
  translations: {},
  type: 'simple',
  combo_price: '',
  combo_entries: [],
  variant_option_groups: [],
  variant_skus: [],
  tag_uuids: [],
})

/** Available tags for restaurant menu items (from GET /api/menu-item-tags). */
const availableTags = ref([])
const selectedTagUuids = computed(() => Array.isArray(form.tag_uuids) ? [...form.tag_uuids] : [])

function toggleTag(uuid) {
  const list = Array.isArray(form.tag_uuids) ? [...form.tag_uuids] : []
  const idx = list.indexOf(uuid)
  if (idx === -1) list.push(uuid)
  else list.splice(idx, 1)
  form.tag_uuids = list
}

/** True only in restaurant context when this item is a catalog reference. Menu items (catalog) context never uses overrides. */
const itemFromCatalog = computed(() => !!restaurant.value && !!catalogSourceUuid.value)

function formatBasePrice(price) {
  const currency = restaurant.value?.currency ?? 'USD'
  return formatCurrency(price, currency)
}

/** Catalog item by uuid for combo entry names (standalone edit). */
const catalogItemByUuid = computed(() => {
  const list = catalogItemsForSummary.value
  const map = {}
  for (const item of list) {
    if (item?.uuid) map[item.uuid] = item
  }
  return map
})

function firstLocaleFromItem(item) {
  if (!item?.translations) return 'en'
  const keys = Object.keys(item.translations)
  return keys[0] ?? 'en'
}

function comboEntryName(entry) {
  const item = catalogItemByUuid.value[entry?.menu_item_uuid]
  if (!item) return entry?.menu_item_uuid ? `Item ${String(entry.menu_item_uuid).slice(0, 8)}…` : '—'
  const loc = item.effectiveName ? firstLocaleFromItem(item) : (Object.keys(item.translations || {})[0] ?? 'en')
  return item.effectiveName ? item.effectiveName(loc) : (item.translations?.[loc]?.name ?? '—')
}

function comboEntryVariantLabel(entry) {
  if (!entry?.variant_uuid) return ''
  const item = catalogItemByUuid.value[entry.menu_item_uuid]
  if (!item?.variant_skus) return ''
  const sku = item.variant_skus.find((s) => s.uuid === entry.variant_uuid)
  if (!sku) return ''
  return sku.displayLabel ? sku.displayLabel(item.variantOptionGroupNames) : Object.values(sku.option_values || {}).join(', ')
}

function variantSkuLabel(sku) {
  if (sku?.displayLabel) return sku.displayLabel()
  const ov = sku?.option_values
  if (!ov || typeof ov !== 'object') return '—'
  return Object.values(ov).filter(Boolean).join(', ')
}

function catalogEditComboItemName(catItem) {
  const loc = firstLocaleFromItem(catItem)
  return catItem.effectiveName ? catItem.effectiveName(loc) : (catItem.translations?.[loc]?.name ?? '—')
}

function catalogEditItemHasVariants(menuItemUuid) {
  const item = catalogItemsForSummary.value.find((i) => i.uuid === menuItemUuid)
  return item?.type === 'with_variants' && Array.isArray(item?.variant_skus) && item.variant_skus.length > 0
}

function catalogEditVariantSkus(menuItemUuid) {
  const item = catalogItemsForSummary.value.find((i) => i.uuid === menuItemUuid)
  return item?.variant_skus ?? []
}

const ADD_THROTTLE_MS = 250
let lastComboEntryAdd = 0
let lastOptionGroupAdd = 0

function addComboEntry() {
  const now = Date.now()
  if (now - lastComboEntryAdd < ADD_THROTTLE_MS) return
  lastComboEntryAdd = now
  form.combo_entries.push({
    menu_item_uuid: '',
    variant_uuid: null,
    quantity: 1,
    modifier_label: '',
  })
}

function removeComboEntry(idx) {
  form.combo_entries.splice(idx, 1)
}

function cartesianProductEdit(groups) {
  if (!groups.length) return []
  const [first, ...rest] = groups
  const firstCombos = (first?.values ?? []).map((v) => ({ [first?.name ?? '']: v }))
  if (rest.length === 0) return firstCombos
  const restCombos = cartesianProductEdit(rest)
  return firstCombos.flatMap((opt) => restCombos.map((r) => ({ ...opt, ...r })))
}

function updateEditCartesianSkus() {
  const groups = form.variant_option_groups.filter((g) => (g.name ?? '').trim() && (g.values ?? []).length > 0)
  const combos = cartesianProductEdit(groups)
  const existingByKey = {}
  for (const row of form.variant_skus) {
    const key = JSON.stringify(row.option_values || row)
    if (row.price !== '' && row.price != null && !Number.isNaN(Number(row.price))) existingByKey[key] = row.price
  }
  form.variant_skus = combos.map((opt) => ({
    option_values: opt,
    label: Object.values(opt).filter(Boolean).join(', '),
    price: existingByKey[JSON.stringify(opt)] ?? '',
  }))
}

function addEditOptionGroup() {
  const now = Date.now()
  if (now - lastOptionGroupAdd < ADD_THROTTLE_MS) return
  lastOptionGroupAdd = now
  form.variant_option_groups.push({ name: '', values: [], valuesText: '' })
}

function removeEditOptionGroup(idx) {
  form.variant_option_groups.splice(idx, 1)
  updateEditCartesianSkus()
}

function syncEditGroupValues(gIdx) {
  const grp = form.variant_option_groups[gIdx]
  if (!grp) return
  const text = (grp.valuesText ?? '').trim()
  grp.values = text ? text.split(/[\n,]+/).map((v) => v.trim()).filter(Boolean) : []
  updateEditCartesianSkus()
}

const installedLanguages = computed(() => restaurant.value?.languages ?? [])

/** On create: only default language. On edit: all installed languages. Standalone edit: locales from form.translations. */
const formLocales = computed(() => {
  if (standaloneItem.value) {
    const keys = Object.keys(form.translations)
    return keys.length ? keys : ['en']
  }
  const def = restaurant.value?.default_locale
  const all = installedLanguages.value
  if (isEdit.value) return all
  return def ? [def] : []
})

function buildTranslations() {
  const locs = standaloneItem.value ? Object.keys(form.translations) : installedLanguages.value
  const out = {}
  for (const loc of locs) {
    const t = form.translations[loc]
    out[loc] = {
      name: t?.name ?? '',
      description: t?.description ?? null,
    }
    if (out[loc].description === '') out[loc].description = null
  }
  return out
}

function validate() {
  const err = {}
  const defaultLoc = restaurant.value?.default_locale ?? (standaloneItem.value ? (Object.keys(form.translations)[0] ?? 'en') : 'en')
  if (form.translations[defaultLoc]) {
    const name = (form.translations[defaultLoc].name ?? '').trim()
    if (!name) err[`translations.${defaultLoc}.name`] = 'Name is required for the default language.'
  }
  if (itemFromCatalog.value) {
    const p = form.price_override === '' || form.price_override == null ? null : Number(form.price_override)
    if (p !== null && (Number.isNaN(p) || p < 0)) err.price_override = 'Price must be 0 or greater.'
  } else if (standaloneItem.value && form.type === 'combo') {
    const p = form.combo_price === '' || form.combo_price == null ? null : Number(form.combo_price)
    if (p !== null && (Number.isNaN(p) || p < 0)) err.combo_price = 'Combo price must be 0 or greater.'
    if (!form.combo_entries.length) err.combo_entries = 'Add at least one combo entry.'
    form.combo_entries.forEach((entry, idx) => {
      if (!(entry.menu_item_uuid ?? '').trim()) err[`combo_entries.${idx}.menu_item_uuid`] = 'Select a menu item.'
      else if (catalogEditItemHasVariants(entry.menu_item_uuid) && !(entry.variant_uuid ?? '')) {
        err[`combo_entries.${idx}.variant_uuid`] = 'Select a variant for this item.'
      }
      const q = entry.quantity != null ? Number(entry.quantity) : 1
      if (Number.isNaN(q) || q < 1) err[`combo_entries.${idx}.quantity`] = 'Quantity must be at least 1.'
    })
  } else if (standaloneItem.value && form.type === 'with_variants') {
    const groups = form.variant_option_groups.filter((g) => (g.name ?? '').trim() && (g.values ?? []).length > 0)
    if (!groups.length) err.variant_option_groups = 'Add at least one option group with values.'
    const missingPrice = form.variant_skus.find((s) => s.price === '' || s.price == null || Number.isNaN(Number(s.price)) || Number(s.price) < 0)
    if (missingPrice && form.variant_skus.length > 0) err.variant_skus = 'Set a price for every variant.'
  } else if (!itemFromCatalog.value) {
    const p = form.price === '' || form.price == null ? null : Number(form.price)
    if (p !== null && (Number.isNaN(p) || p < 0)) err.price = 'Price must be 0 or greater.'
  }
  fieldErrors.value = err
  return Object.keys(err).length === 0
}

function buildTranslationOverrides() {
  const overrides = {}
  const base = baseTranslations.value
  for (const loc of Object.keys(form.translations)) {
    const t = form.translations[loc] ?? {}
    const b = base[loc] ?? {}
    const name = (t.name ?? '').trim()
    const desc = t.description ?? null
    const baseName = (b.name ?? '').trim()
    const baseDesc = b.description ?? null
    if (name !== baseName || desc !== baseDesc) {
      overrides[loc] = { name: name || baseName, description: desc !== baseDesc ? desc : null }
    }
  }
  return overrides
}

function setInitialEditState() {
  if (!isEdit.value) return
  const snap = {
    comboEntriesCount: form.combo_entries.length,
    variantOptionGroupsCount: form.variant_option_groups.length,
    variantSkusCount: form.variant_skus.length,
    translations: {},
  }
  for (const loc of Object.keys(form.translations || {})) {
    const t = form.translations[loc]
    snap.translations[loc] = { name: (t?.name ?? '').trim(), description: t?.description ?? null }
  }
  initialEditState.value = snap
}

function hasSensitiveChanges() {
  const init = initialEditState.value
  if (!init) return false
  if (form.combo_entries.length < init.comboEntriesCount) return true
  if (form.variant_option_groups.length < init.variantOptionGroupsCount) return true
  if (form.variant_skus.length < init.variantSkusCount) return true
  for (const loc of Object.keys(init.translations || {})) {
    const t = form.translations?.[loc]
    const name = (t?.name ?? '').trim()
    const desc = t?.description ?? null
    if (name !== (init.translations[loc]?.name ?? '') || desc !== (init.translations[loc]?.description ?? null)) return true
  }
  for (const loc of Object.keys(form.translations || {})) {
    if (init.translations[loc]) continue
    const t = form.translations[loc]
    if ((t?.name ?? '').trim() || t?.description != null) return true
  }
  return false
}

function handleSubmit() {
  error.value = ''
  fieldErrors.value = {}
  if (!validate()) return
  if (isEdit.value && hasSensitiveChanges()) {
    showSaveConfirmModal.value = true
    return
  }
  doActualSubmit()
}

async function confirmSaveAndSubmit() {
  showSaveConfirmModal.value = false
  doActualSubmit()
}

async function doActualSubmit() {
  saving.value = true
  try {
    let payload = { sort_order: form.sort_order }
    // Menu items (catalog) context: type, translations, and type-specific fields.
    if (isMenuItemsModule.value && standaloneItem.value) {
      payload.translations = buildTranslations()
      payload.type = form.type
      if (form.type === 'simple') {
        const p = form.price === '' || form.price == null ? null : Number(form.price)
        if (p != null && !Number.isNaN(p)) payload.price = p
      } else if (form.type === 'combo') {
        const cp = form.combo_price === '' || form.combo_price == null ? null : Number(form.combo_price)
        if (cp != null && !Number.isNaN(cp)) payload.combo_price = cp
        payload.combo_entries = form.combo_entries.map((e) => ({
          menu_item_uuid: e.menu_item_uuid,
          variant_uuid: e.variant_uuid || null,
          quantity: Math.max(1, Number(e.quantity) || 1),
          modifier_label: (e.modifier_label ?? '').trim() || null,
        }))
      } else if (form.type === 'with_variants') {
        payload.variant_option_groups = form.variant_option_groups
          .filter((g) => (g.name ?? '').trim() && (g.values ?? []).length > 0)
          .map((g) => ({ name: g.name.trim(), values: g.values }))
        payload.variant_skus = form.variant_skus.map((s) => ({
          option_values: s.option_values,
          price: Number(s.price),
          image_url: s.image_url ?? null,
        }))
      }
    } else if (!isMenuItemsModule.value && itemFromCatalog.value) {
      payload.price_override = form.price_override === '' || form.price_override == null ? null : Number(form.price_override)
      payload.translation_overrides = buildTranslationOverrides()
    } else {
      payload.translations = buildTranslations()
      payload.price = form.price === '' || form.price == null ? null : Number(form.price)
      if (payload.price !== null && Number.isNaN(payload.price)) delete payload.price
    }
    if (form.category_uuid != null && form.category_uuid !== '' && restaurant.value) payload.category_uuid = form.category_uuid
    if (!restaurant.value && Array.isArray(form.tag_uuids)) payload.tag_uuids = form.tag_uuids
    if (isEdit.value) {
      if (standaloneItem.value) {
        await menuItemService.update(itemUuid.value, payload)
        toastStore.success('Menu item updated.')
        await loadStandaloneMenuItem()
      } else {
        await restaurantService.updateMenuItem(uuid.value, itemUuid.value, payload)
        toastStore.success('Menu item updated.')
        await loadMenuItem()
      }
    } else {
      await restaurantService.createMenuItem(uuid.value, payload)
      toastStore.success('Menu item created.')
      if (route.query.return === 'category-items' && route.query.category_uuid) {
        router.push({
          name: 'CategoryMenuItems',
          params: { uuid: uuid.value, categoryUuid: route.query.category_uuid },
          query: route.query.name ? { name: route.query.name } : undefined,
        })
      } else {
        router.push(isMenuItemsModule.value ? { name: 'MenuItems' } : { name: 'RestaurantMenuItems', params: { uuid: uuid.value } })
      }
    }
  } catch (e) {
    const errs = getValidationErrors(e)
    if (Object.keys(errs).length > 0) fieldErrors.value = errs
    error.value = e?.response?.data?.message ?? normalizeApiError(e).message
  } finally {
    saving.value = false
  }
}

async function revertToBase() {
  if (!uuid.value || !itemUuid.value || !itemFromCatalog.value) return
  error.value = ''
  reverting.value = true
  try {
    await restaurantService.updateMenuItem(uuid.value, itemUuid.value, { revert_to_base: true })
    toastStore.success('Reverted to base value.')
    await loadMenuItem()
  } catch (e) {
    error.value = e?.response?.data?.message ?? normalizeApiError(e).message
  } finally {
    reverting.value = false
  }
}

function setVariantImageInputRef(uuid, el) {
  if (el) variantImageInputRefs.value[uuid] = el
  else delete variantImageInputRefs.value[uuid]
}

/** Catalog context only: upload/remove item or variant image via menuItemService. */
async function removeItemImage() {
  if (!itemUuid.value || !isMenuItemsModule.value) return
  uploadingImageFor.value = 'item'
  try {
    await menuItemService.deleteImage(itemUuid.value)
    toastStore.success('Image removed.')
    await loadStandaloneMenuItem()
  } catch (e) {
    toastStore.error(e?.response?.data?.message ?? normalizeApiError(e).message)
  } finally {
    uploadingImageFor.value = null
  }
}

function onItemImageSelect(event) {
  const file = event.target?.files?.[0]
  if (!file || !itemUuid.value || !isMenuItemsModule.value) return
  uploadingImageFor.value = 'item'
  menuItemService.uploadImage(itemUuid.value, file)
    .then(() => {
      toastStore.success('Image updated.')
      return loadStandaloneMenuItem()
    })
    .catch((e) => toastStore.error(e?.response?.data?.message ?? normalizeApiError(e).message))
    .finally(() => { uploadingImageFor.value = null })
  event.target.value = ''
}

function onVariantImageSelect(skuUuid, event) {
  const file = event.target?.files?.[0]
  if (!file || !itemUuid.value || !skuUuid || !isMenuItemsModule.value) return
  uploadingImageFor.value = skuUuid
  menuItemService.uploadVariantImage(itemUuid.value, skuUuid, file)
    .then(() => {
      toastStore.success('Image updated.')
      return loadStandaloneMenuItem()
    })
    .catch((e) => toastStore.error(e?.response?.data?.message ?? normalizeApiError(e).message))
    .finally(() => { uploadingImageFor.value = null })
  event.target.value = ''
}

async function removeVariantImage(skuUuid) {
  if (!itemUuid.value || !skuUuid || !isMenuItemsModule.value) return
  uploadingImageFor.value = skuUuid
  try {
    await menuItemService.deleteVariantImage(itemUuid.value, skuUuid)
    toastStore.success('Image removed.')
    await loadStandaloneMenuItem()
  } catch (e) {
    toastStore.error(e?.response?.data?.message ?? normalizeApiError(e).message)
  } finally {
    uploadingImageFor.value = null
  }
}

function onBeautifyAiClick() {
  // v-require-paid shows modal when not paid; when paid this runs — AI beautification to be implemented
  toastStore.info('AI image beautification coming soon.')
}

async function translateLocale(targetLoc) {
  const defaultLoc = restaurant.value?.default_locale
  if (!defaultLoc || targetLoc === defaultLoc) return
  const name = (form.translations[defaultLoc]?.name ?? '').trim()
  const desc = (form.translations[defaultLoc]?.description ?? '').trim()
  if (!name && !desc) {
    toastStore.error('Fill in name or description in the default language first.')
    return
  }
  translatingLocale.value = targetLoc
  error.value = ''
  try {
    let usedFallback = false
    if (name) {
      const resName = await localeService.translate({ text: name, from_locale: defaultLoc, to_locale: targetLoc })
      if (resName.translated_text != null) {
        if (!form.translations[targetLoc]) form.translations[targetLoc] = { name: '', description: null }
        form.translations[targetLoc].name = resName.translated_text
        if (resName.fallback) usedFallback = true
      }
    }
    if (desc) {
      const resDesc = await localeService.translate({ text: desc, from_locale: defaultLoc, to_locale: targetLoc })
      if (resDesc.translated_text != null) {
        if (!form.translations[targetLoc]) form.translations[targetLoc] = { name: '', description: null }
        form.translations[targetLoc].description = resDesc.translated_text
        if (resDesc.fallback) usedFallback = true
      }
    }
    if (usedFallback) {
      toastStore.info('Translation not available for this language. Original text shown — you can edit it.')
    } else {
      toastStore.success('Translation applied. Review and save.')
    }
  } catch (e) {
    error.value = normalizeApiError(e).message
  } finally {
    translatingLocale.value = null
  }
}

async function loadAvailableTags() {
  if (!uuid.value) return
  try {
    const res = await menuItemTagService.list()
    const list = Array.isArray(res?.data) ? res.data : []
    availableTags.value = list.map((t) => ({ uuid: t.uuid, color: t.color, icon: t.icon, text: t.text }))
  } catch {
    availableTags.value = []
  }
}

async function loadRestaurant() {
  if (!uuid.value) {
    loading.value = false
    return
  }
  try {
    const res = await restaurantService.get(uuid.value)
    restaurant.value = res?.data != null ? Restaurant.fromApi(res).toJSON() : null
    if (restaurant.value) {
      const langRes = await restaurantService.getLanguages(uuid.value).catch(() => ({ data: [restaurant.value.default_locale || 'en'] }))
      restaurant.value.languages = Array.isArray(langRes?.data) ? langRes.data : [restaurant.value.default_locale || 'en']
    }
    if (!isMenuItemsModule.value) breadcrumbStore.setRestaurantName(restaurant.value?.name ?? null)
    if (restaurant.value && !isEdit.value) initFormFromRestaurant()
  } catch (e) {
    if (e?.response?.status === 404) restaurant.value = null
  } finally {
    loading.value = false
  }
}

function initFormFromRestaurant() {
  const def = restaurant.value?.default_locale ?? 'en'
  form.sort_order = 0
  form.category_uuid = route.query.category_uuid ?? null
  form.price = ''
  form.price_override = ''
  form.translations = {}
  form.tag_uuids = []
  if (def) form.translations[def] = { name: '', description: null }
  selectedLocale.value = def
}

async function loadMenuItem() {
  if (!uuid.value || !itemUuid.value) return
  menuItemFromApi.value = null
  try {
    const res = await restaurantService.getMenuItem(uuid.value, itemUuid.value)
    const item = res?.data != null ? MenuItem.fromApi(res) : null
    if (!item) return
    form.sort_order = item.sort_order ?? 0
    form.category_uuid = item.category_uuid ?? null
    const trans = item.translations ?? {}
    const locs = restaurant.value?.languages ?? []
    form.translations = {}
    for (const loc of locs) {
      const t = trans[loc]
      form.translations[loc] = {
        name: t?.name ?? '',
        description: t?.description ?? null,
      }
    }
    if (item.source_menu_item_uuid) {
      catalogSourceUuid.value = item.source_menu_item_uuid
      baseTranslations.value = item.base_translations ?? {}
      basePrice.value = item.base_price ?? null
      hasOverrides.value = item.has_overrides ?? false
      form.price_override = item.price_override != null ? String(item.price_override) : ''
      form.price = ''
    } else {
      catalogSourceUuid.value = null
      baseTranslations.value = {}
      basePrice.value = null
      hasOverrides.value = false
      form.price_override = ''
      form.price = item.price != null ? String(item.price) : ''
    }
    form.tag_uuids = Array.isArray(item.tags) ? item.tags.map((t) => t.uuid).filter(Boolean) : []
    const defLoc = restaurant.value?.default_locale ?? locs[0]
    selectedLocale.value = locs.includes(selectedLocale.value) ? selectedLocale.value : (defLoc ?? locs[0] ?? 'en')
    const defaultName = defLoc ? (form.translations[defLoc]?.name ?? '') : ''
    breadcrumbStore.setMenuItemName(defaultName.trim() || null)
    setInitialEditState()
  } catch (e) {
    if (e?.response?.status === 404) restaurant.value = null
  }
}

async function loadStandaloneMenuItem() {
  if (!itemUuid.value) return
  try {
    const res = await menuItemService.get(itemUuid.value)
    const item = res?.data != null ? MenuItem.fromApi(res) : null
    if (!item) return
    standaloneItem.value = item.toJSON()
    catalogSourceUuid.value = null
    baseTranslations.value = {}
    basePrice.value = null
    hasOverrides.value = false
    form.sort_order = item.sort_order ?? 0
    form.category_uuid = null
    form.type = item.type ?? 'simple'
    form.price = item.price != null ? String(item.price) : ''
    form.price_override = ''
    form.combo_price = item.combo_price != null ? String(item.combo_price) : ''
    form.combo_entries = (item.combo_entries ?? []).map((e) => ({
      menu_item_uuid: e.menu_item_uuid ?? '',
      variant_uuid: e.variant_uuid ?? null,
      quantity: e.quantity ?? 1,
      modifier_label: e.modifier_label ?? '',
    }))
    form.variant_option_groups = (item.variant_option_groups ?? []).map((g) => ({
      name: g.name ?? '',
      values: Array.isArray(g.values) ? [...g.values] : [],
      valuesText: Array.isArray(g.values) ? g.values.join(', ') : '',
    }))
    form.variant_skus = (item.variant_skus ?? []).map((s) => ({
      ...s,
      option_values: s.option_values ?? {},
      label: Object.values(s.option_values || {}).filter(Boolean).join(', '),
      price: s.price != null ? s.price : '',
    }))
    const trans = item.translations ?? {}
    form.translations = {}
    for (const loc of Object.keys(trans)) {
      const t = trans[loc]
      form.translations[loc] = {
        name: t?.name ?? '',
        description: t?.description ?? null,
      }
    }
    const firstLoc = Object.keys(form.translations)[0] ?? 'en'
    selectedLocale.value = Object.keys(form.translations).includes(selectedLocale.value) ? selectedLocale.value : firstLoc
    const defaultName = firstLoc ? (form.translations[firstLoc]?.name ?? '') : ''
    breadcrumbStore.setMenuItemName(defaultName.trim() || null)
    if (item.type === 'combo') {
      const listRes = await menuItemService.list()
      const raw = listRes.data ?? []
      catalogItemsForSummary.value = raw.map((i) => MenuItem.fromApi({ data: i }))
    } else {
      catalogItemsForSummary.value = []
    }
    menuItemFromApi.value = {
      ...standaloneItem.value,
      image_url: item.image_url ?? null,
      variant_skus: (item.variant_skus ?? []).map((s) => ({ ...s, image_url: s.image_url ?? null })),
    }
    setInitialEditState()
  } catch (e) {
    if (e?.response?.status === 404) standaloneItem.value = null
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  if (isMenuItemsModule.value && !isEdit.value) {
    router.replace({ name: 'MenuItems' })
    return
  }
  if (isMenuItemsModule.value && isEdit.value && !uuid.value) {
    await loadStandaloneMenuItem()
    return
  }
  await loadRestaurant()
  if (isEdit.value && restaurant.value && itemUuid.value) await loadMenuItem()
  if (isEdit.value && !restaurant.value && !standaloneItem.value) loading.value = false
})

watch([uuid, itemUuid], async () => {
  if (uuid.value) await loadRestaurant()
  if (isEdit.value && uuid.value && itemUuid.value && restaurant.value) await loadMenuItem()
  if (isMenuItemsModule.value && isEdit.value && !uuid.value && itemUuid.value) await loadStandaloneMenuItem()
})

watch(formLocales, (locs) => {
  if (locs.length && !locs.includes(selectedLocale.value)) {
    const def = restaurant.value?.default_locale ?? (standaloneItem.value ? null : 'en')
    selectedLocale.value = (def && locs.includes(def)) ? def : locs[0]
  }
}, { immediate: true })

watch(() => form.type, async (type) => {
  if (isMenuItemsModule.value && standaloneItem.value && type === 'combo' && catalogItemsForSummary.value.length === 0) {
    try {
      const listRes = await menuItemService.list()
      const raw = listRes.data ?? []
      catalogItemsForSummary.value = raw.map((i) => MenuItem.fromApi({ data: i }))
    } catch {
      catalogItemsForSummary.value = []
    }
  }
})
</script>
