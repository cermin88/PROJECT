<?php

namespace App\Http\Controllers\Ecosystem;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Partner;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $q = Product::with('partner');
        if ($s = $request->query('q')) {
            $q->where('name', 'like', "%{$s}%");
        }
        foreach (['category', 'status', 'partner_id'] as $f) {
            if ($v = $request->query($f)) {
                $q->where($f, $v);
            }
        }
        return view('ecosystem.products.index', [
            'products' => $q->orderBy('name')->paginate(config('csa.per_page'))->withQueryString(),
            'partners' => Partner::active()->orderBy('name')->get(),
            'canEdit' => Access::editsEcosystem(auth()->user()),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(Access::editsEcosystem(auth()->user()), 403);
        $p = Product::create($this->validated($request));
        AuditLog::record('PRODUCT_CREATED', 'product', $p->id, ['name' => $p->name]);
        return redirect()->route('products.index')->with('ok', "Product {$p->ref()} created.");
    }

    public function edit(Product $product)
    {
        abort_unless(Access::editsEcosystem(auth()->user()), 403);
        return view('ecosystem.products.edit', ['product' => $product, 'partners' => Partner::active()->orderBy('name')->get()]);
    }

    public function update(Request $request, Product $product)
    {
        abort_unless(Access::editsEcosystem(auth()->user()), 403);
        $product->update($this->validated($request));
        AuditLog::record('PRODUCT_UPDATED', 'product', $product->id, ['changed' => array_keys($product->getChanges())]);
        return redirect()->route('products.index')->with('ok', 'Product saved.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'category' => ['required', Rule::in(Product::CATEGORIES)],
            'partner_id' => ['nullable', Rule::exists('partners', 'id')->where('archived', 0)],
            'status' => ['required', Rule::in(Product::STATUSES)],
            'description' => 'nullable|string|max:2000',
        ]) + ['partner_id' => null, 'description' => null];
    }
}
