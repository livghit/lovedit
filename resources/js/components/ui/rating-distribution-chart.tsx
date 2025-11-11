import React from 'react';
import { AreaChart, Area, XAxis, YAxis, CartesianGrid } from 'recharts';
import {
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
    type ChartConfig,
} from '@/components/ui/chart';

type Distribution = Record<number, number>;

export interface RatingDistributionChartProps {
    distribution: Distribution;
    className?: string;
}

function isAllZero(dist: Distribution): boolean {
    return [1, 2, 3, 4, 5].every((k) => (dist[k] ?? 0) === 0);
}

export function RatingDistributionChart({ distribution, className }: RatingDistributionChartProps) {
    const normalized: Distribution = {
        1: distribution?.[1] ?? 0,
        2: distribution?.[2] ?? 0,
        3: distribution?.[3] ?? 0,
        4: distribution?.[4] ?? 0,
        5: distribution?.[5] ?? 0,
    };

    if (isAllZero(normalized)) {
        return (
            <div className={['mt-4 text-xs text-muted-foreground', className].filter(Boolean).join(' ')}>
                No ratings yet.
            </div>
        );
    }

    const data = [1, 2, 3, 4, 5].map((star) => ({ star, count: normalized[star] }));

    // Use a richer red via the existing destructive token
    const chartConfig: ChartConfig = {
        count: {
            label: 'Ratings',
            color: 'var(--destructive)',
        },
    };

    return (
        <ChartContainer
            config={chartConfig}
            className={['mt-4 h-32 w-full', className].filter(Boolean).join(' ')}
        >
            <AreaChart data={data} margin={{ top: 10, right: 6, left: 6, bottom: 0 }}>
                <defs>
                    <linearGradient id="fillCount" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="12%" stopColor="var(--color-count)" stopOpacity={0.9} />
                        <stop offset="100%" stopColor="var(--color-count)" stopOpacity={0.12} />
                    </linearGradient>
                </defs>
                <CartesianGrid stroke="var(--border)" strokeOpacity={0.15} vertical={false} />
                <XAxis
                    dataKey="star"
                    tickLine={false}
                    axisLine={false}
                    tick={{ fontSize: 10 }}
                    padding={{ left: 4, right: 4 }}
                />
                <YAxis hide />
                <Area
                    type="monotone"
                    dataKey="count"
                    stroke="var(--color-count)"
                    fill="url(#fillCount)"
                    strokeWidth={2}
                    activeDot={{ r: 4 }}
                />
                <ChartTooltip
                    cursor={{ stroke: 'var(--border)', strokeOpacity: 0.4 }}
                    content={
                        <ChartTooltipContent
                            indicator="line"
                            labelFormatter={(_, payload) => {
                                const p = payload?.[0];
                                if (!p) return null;
                                return `${p.payload.star} Star${p.payload.star === 1 ? '' : 's'}`;
                            }}
                            formatter={(value) => (
                                <div className="flex w-full justify-between">
                                    <span className="text-muted-foreground">Ratings</span>
                                    <span className="font-mono font-medium tabular-nums">{(value as number).toLocaleString()}</span>
                                </div>
                            )}
                            hideIndicator
                        />
                    }
                />
            </AreaChart>
        </ChartContainer>
    );
}

export default RatingDistributionChart;
