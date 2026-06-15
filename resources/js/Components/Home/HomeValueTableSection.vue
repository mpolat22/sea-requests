<script setup>
import HomeSectionHeader from './HomeSectionHeader.vue';

defineProps({
    eyebrow: {
        type: String,
        default: '',
    },
    title: {
        type: String,
        required: true,
    },
    text: {
        type: String,
        default: '',
    },
    scopeLabel: {
        type: String,
        required: true,
    },
    buyerLabel: {
        type: String,
        required: true,
    },
    supplierLabel: {
        type: String,
        required: true,
    },
    rows: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <section class="home-section">
        <div class="value-shell">
            <div class="value-surface">
                <HomeSectionHeader :eyebrow="eyebrow" :title="title" :text="text" />

                <div class="table-shell desktop-table">
                    <table class="value-table">
                        <colgroup>
                            <col class="scope-col">
                            <col class="party-col">
                            <col class="party-col">
                        </colgroup>
                        <thead>
                            <tr>
                                <th>
                                    <span class="header-chip">
                                        <span class="header-chip-icon">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <path d="M7 4.5h7l3 3V19a1.5 1.5 0 0 1-1.5 1.5h-8A1.5 1.5 0 0 1 6 19V6a1.5 1.5 0 0 1 1-1.5Z" />
                                                <path d="M14 4.5V8h3" />
                                            </svg>
                                        </span>
                                        {{ scopeLabel }}
                                    </span>
                                </th>
                                <th>
                                    <span class="header-chip buyer-chip">
                                        <span class="header-chip-icon">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <circle cx="12" cy="8" r="3" />
                                                <path d="M6.5 19a5.5 5.5 0 0 1 11 0" />
                                            </svg>
                                        </span>
                                        {{ buyerLabel }}
                                    </span>
                                </th>
                                <th>
                                    <span class="header-chip supplier-chip">
                                        <span class="header-chip-icon">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <path d="M3 21h18" />
                                                <path d="M5 21V7l7-4 7 4v14" />
                                                <path d="M9 9h.01" />
                                                <path d="M9 13h.01" />
                                                <path d="M9 17h.01" />
                                            </svg>
                                        </span>
                                        {{ supplierLabel }}
                                    </span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in rows" :key="row.key ?? row.label">
                                <th scope="row">{{ row.label }}</th>
                                <td>{{ row.buyer }}</td>
                                <td>{{ row.supplier }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mobile-cards">
                    <article v-for="row in rows" :key="row.key ?? row.label" class="mobile-card">
                        <strong class="mobile-card-title">{{ row.label }}</strong>

                        <div class="mobile-field">
                            <span class="mobile-kicker">{{ buyerLabel }}</span>
                            <p>{{ row.buyer }}</p>
                        </div>

                        <div class="mobile-field">
                            <span class="mobile-kicker">{{ supplierLabel }}</span>
                            <p>{{ row.supplier }}</p>
                        </div>
                    </article>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.mobile-cards {
    display: none;
}

.value-shell {
    padding: 32px 36px;
    border: 1px solid rgba(4, 21, 31, 0.08);
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.78);
    box-shadow: 0 24px 44px rgba(15, 23, 42, 0.08);
}

.value-surface {
    padding: 24px;
    border-radius: 10px;
    background: #f8fafb;
}

.table-shell {
    overflow-x: auto;
    border: 1px solid rgba(4, 21, 31, 0.08);
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.92);
    box-shadow: 0 18px 34px rgba(15, 23, 42, 0.06);
}

.value-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 760px;
}

.scope-col {
    width: 24%;
}

.party-col {
    width: 38%;
}

.value-table th,
.value-table td {
    padding: 18px 20px;
    text-align: left;
    vertical-align: top;
    border-bottom: 1px solid rgba(4, 21, 31, 0.08);
}

.value-table thead th {
    background: rgba(248, 250, 252, 0.94);
    color: #04151f;
    font-size: 0.86rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

.header-chip {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    min-height: 36px;
    padding: 0 12px;
    border-radius: 999px;
    background: rgba(14, 116, 144, 0.08);
    color: #0e7490;
    font-size: 0.74rem;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
}

.header-chip-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 18px;
    height: 18px;
    color: currentColor;
}

.header-chip-icon svg {
    width: 14px;
    height: 14px;
    display: block;
}

.buyer-chip {
    background: rgba(37, 99, 235, 0.08);
    color: #2563eb;
}

.supplier-chip {
    background: rgba(15, 118, 110, 0.08);
    color: #0f766e;
}

.value-table tbody th {
    color: #04151f;
    font-size: 0.98rem;
    font-weight: 700;
    line-height: 1.45;
    background: rgba(255, 255, 255, 0.92);
}

.value-table tbody td {
    color: rgba(4, 21, 31, 0.76);
    font-size: 0.96rem;
    line-height: 1.7;
    background: rgba(255, 255, 255, 0.92);
}

.value-table tbody tr:nth-child(even) th,
.value-table tbody tr:nth-child(even) td {
    background: rgba(248, 250, 252, 0.72);
}

.value-table tbody tr:last-child th,
.value-table tbody tr:last-child td {
    border-bottom: none;
}

@media (max-width: 900px) {
    .value-shell {
        padding: 28px;
    }

    .desktop-table {
        display: none;
    }

    .mobile-cards {
        display: grid;
        gap: 14px;
    }

    .mobile-card {
        display: grid;
        gap: 14px;
        padding: 18px;
        border: 1px solid rgba(4, 21, 31, 0.08);
        border-radius: 10px;
        background: rgba(255, 255, 255, 0.92);
        box-shadow: 0 18px 34px rgba(15, 23, 42, 0.06);
    }

    .mobile-card-title {
        color: #04151f;
        font-size: 1rem;
        line-height: 1.35;
    }

    .mobile-field {
        display: grid;
        gap: 6px;
    }

    .mobile-kicker {
        color: rgba(71, 85, 105, 0.92);
        font-size: 0.75rem;
        font-weight: 700;
        letter-spacing: 0.14em;
        text-transform: uppercase;
    }

    .mobile-field p {
        margin: 0;
        color: rgba(4, 21, 31, 0.76);
        font-size: 0.95rem;
        line-height: 1.7;
    }
}

@media (max-width: 720px) {
    .value-shell,
    .value-surface {
        padding: 20px;
    }
}
</style>
