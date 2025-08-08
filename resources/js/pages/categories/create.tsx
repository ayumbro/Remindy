import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Categories',
        href: '/categories',
    },
    {
        title: 'Create',
        href: '/categories/create',
    },
];

interface CreateCategoryProps {
    defaultColors: string[];
}

interface CategoryForm {
    name: string;
    color: string;
    description: string;
    is_active: boolean;
}

export default function CreateCategory({ defaultColors = [] }: CreateCategoryProps) {
    const [selectedColor, setSelectedColor] = useState<string>('#000000');

    const { data, setData, post, processing, errors, reset } = useForm<CategoryForm>({
        name: '',
        color: '',
        description: '',
        is_active: true,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        // Client-side validation
        if (!data.name.trim()) {
            return;
        }

        post(route('categories.store'), {
            onSuccess: () => {
                reset();
                setSelectedColor('#000000');
            },
        });
    };

    const handleColorSelect = (color: string) => {
        setSelectedColor(color);
        setData('color', color);
    };

    const handleCustomColorChange = (color: string) => {
        setSelectedColor(color);
        // Only set the color in form data if it's a valid hex color or empty
        if (color === '' || /^#[0-9A-Fa-f]{6}$/.test(color)) {
            setData('color', color);
        }
    };

    const handleColorInputChange = (color: string) => {
        // Color input always provides a valid hex color
        setSelectedColor(color);
        setData('color', color);
    };

    const handleClearColor = () => {
        setSelectedColor('#000000');
        setData('color', '');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Category" />

            <div className="flex h-full flex-1 flex-col gap-6 p-6">
                <div>
                    <h1 className="text-3xl font-bold">Create Category</h1>
                    <p className="text-muted-foreground">Add a new category to organize your subscriptions</p>
                </div>

                <Card className="max-w-2xl">
                    <CardHeader>
                        <CardTitle>Category Details</CardTitle>
                        <CardDescription>Enter the details of your new category</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-6">
                            {/* Category Name */}
                            <div className="space-y-2">
                                <Label htmlFor="category-name">Category Name *</Label>
                                <Input
                                    id="category-name"
                                    name="name"
                                    type="text"
                                    required
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    disabled={processing}
                                    placeholder="e.g., Entertainment, Productivity, Health & Fitness"
                                    autoComplete="off"
                                />
                                <InputError message={errors.name} />
                            </div>

                            {/* Color Selection */}
                            <div className="space-y-2">
                                <Label>Category Color</Label>
                                <div className="space-y-3">
                                    {/* Predefined Colors */}
                                    <div>
                                        <p className="text-muted-foreground mb-2 text-sm">Choose a preset color:</p>
                                        <div className="flex flex-wrap gap-2">
                                            {defaultColors.map((color) => (
                                                <button
                                                    key={color}
                                                    type="button"
                                                    className={`h-8 w-8 rounded-full border-2 transition-all ${
                                                        selectedColor === color
                                                            ? 'scale-110 border-gray-900'
                                                            : 'border-gray-300 hover:border-gray-500'
                                                    }`}
                                                    style={{ backgroundColor: color }}
                                                    onClick={() => handleColorSelect(color)}
                                                    aria-label={`Select color ${color}`}
                                                />
                                            ))}
                                        </div>
                                    </div>

                                    {/* Custom Color */}
                                    <div>
                                        <p className="text-muted-foreground mb-2 text-sm">Or choose a custom color:</p>
                                        <div className="flex items-center gap-3">
                                            <Input
                                                id="category-color"
                                                name="color"
                                                type="color"
                                                value={selectedColor || '#000000'}
                                                onChange={(e) => handleColorInputChange(e.target.value)}
                                                disabled={processing}
                                                className="h-10 w-16 rounded border p-1"
                                            />
                                            <Input
                                                type="text"
                                                value={data.color}
                                                onChange={(e) => handleCustomColorChange(e.target.value)}
                                                disabled={processing}
                                                placeholder="#FF0000"
                                                className="w-24"
                                                maxLength={7}
                                            />
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={handleClearColor}
                                                disabled={processing}
                                                className="text-xs"
                                            >
                                                Clear
                                            </Button>
                                        </div>
                                        <InputError message={errors.color} />
                                        <p className="text-muted-foreground mt-1 text-xs">Enter a hex color code (e.g., #FF0000)</p>
                                    </div>
                                </div>
                            </div>

                            {/* Description */}
                            <div className="space-y-2">
                                <Label htmlFor="category-description">Description</Label>
                                <Textarea
                                    id="category-description"
                                    name="description"
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    disabled={processing}
                                    placeholder="Brief description of this category"
                                    rows={3}
                                    autoComplete="off"
                                />
                                <InputError message={errors.description} />
                            </div>

                            {/* Active Status */}
                            <div className="flex items-center space-x-2">
                                <Checkbox
                                    id="category-active"
                                    name="is_active"
                                    checked={data.is_active}
                                    onCheckedChange={(checked) => setData('is_active', checked as boolean)}
                                />
                                <Label htmlFor="category-active" className="text-sm font-normal">
                                    Active category
                                </Label>
                            </div>

                            {/* Preview */}
                            {(data.name || selectedColor) && (
                                <div className="space-y-2">
                                    <Label>Preview</Label>
                                    <div className="bg-muted/50 flex items-center gap-2 rounded-lg border p-3">
                                        <div className="h-4 w-4 rounded-full" style={{ backgroundColor: selectedColor || '#6B7280' }} />
                                        <span className="font-medium">{data.name || 'Category Name'}</span>
                                        {data.description && <span className="text-muted-foreground text-sm">- {data.description}</span>}
                                    </div>
                                </div>
                            )}

                            {/* Submit Buttons */}
                            <div className="flex gap-4 pt-4">
                                <Button type="submit" disabled={processing}>
                                    {processing && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
                                    Create Category
                                </Button>
                                <Button type="button" variant="outline" onClick={() => window.history.back()}>
                                    Cancel
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
