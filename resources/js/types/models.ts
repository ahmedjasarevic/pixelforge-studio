export type Wallet = {
    balance_picodollars: number;
    balance: string;
};

export type TokenCounts = {
    input_text: number;
    input_image: number;
    output: number;
    reasoning: number;
};

export type CostEstimate = {
    characters: number;
    tokens: TokenCounts;
    max_tokens: TokenCounts;
    provider_cost: number;
    max_provider_cost: number;
    charge: number;
    max_charge: number;
    provider_cost_formatted: string;
    charge_formatted: string;
    max_provider_cost_formatted: string;
    max_charge_formatted: string;
    markup: number;
    markup_formatted: string;
    max_markup: number;
    max_markup_formatted: string;
    markup_percent: number;
    rates: Record<string, string>;
};

export type GenerationStatus = 'queued' | 'processing' | 'succeeded' | 'failed';

export type Generation = {
    id: number;
    prompt: string;
    size: string;
    quality: string;
    reference_images: string[];
    status: GenerationStatus;
    status_label: string;
    is_final: boolean;
    image_url: string | null;
    image_url_expires_at: string | null;
    estimated_charge: number;
    estimated_charge_formatted: string;
    max_charge: number;
    max_charge_formatted: string;
    charged_amount: number | null;
    charged_amount_formatted: string | null;
    error_code: string | null;
    error_message: string | null;
    usage: {
        input_text_tokens: number;
        input_image_tokens: number;
        output_tokens: number;
        reasoning_tokens: number;
        total_tokens?: number;
    } | null;
    created_at: string | null;
    completed_at: string | null;
};

export type CreditTransaction = {
    id: number;
    type: 'purchase' | 'hold' | 'settlement';
    amount: number;
    amount_formatted: string;
    balance_after_formatted: string;
    description: string | null;
    generation_id: number | null;
    created_at: string | null;
};

export type ReportRow = {
    user_id: number;
    user_name: string;
    user_email: string | null;
    generations: number;
    charged: number;
    charged_formatted: string;
    provider_cost: number;
    provider_cost_formatted: string;
    profit: number;
    profit_formatted: string;
};

export type ReportTotals = {
    generations: number;
    charged: number;
    charged_formatted: string;
    provider_cost: number;
    provider_cost_formatted: string;
    profit: number;
    profit_formatted: string;
};

export type ReportDay = {
    date: string;
    rows: ReportRow[];
    totals: ReportTotals;
    pixelforge_cost: number | null;
    pixelforge_cost_formatted: string | null;
    provider_delta: number | null;
    provider_delta_formatted: string | null;
    reconciled: boolean;
};

export type AdminReport = {
    range: { from: string; to: string };
    days: ReportDay[];
    user_totals: ReportRow[];
    totals: ReportTotals & {
        pixelforge_cost: number | null;
        pixelforge_cost_formatted: string | null;
        provider_delta: number | null;
        provider_delta_formatted: string | null;
        profit_margin_percent: number;
        reconciled: boolean;
    };
    usage_error: string | null;
};
