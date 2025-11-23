<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\QuoteTemplates\StoreQuoteTemplateRequest;
use App\Http\Requests\QuoteTemplates\UpdateQuoteTemplateRequest;
use App\Http\Resources\QuoteTemplateResource;
use App\Models\QuoteTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class QuoteTemplateController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', QuoteTemplate::class);

        $tenantId = $request->user()->tenant_id;
        $query = QuoteTemplate::query()->where('tenant_id', $tenantId);

        // Filter by layout
        if ($layout = $request->query('layout')) {
            $query->byLayout($layout);
        }

        // Filter by default status
        if ($request->has('is_default')) {
            $query->where('is_default', $request->boolean('is_default'));
        }

        // Search functionality
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Sort
        $sortBy = $request->query('sortBy', 'created_at');
        $sortOrder = $request->query('sortOrder', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->query('per_page', 15);
        $templates = $query->paginate($perPage);

        return QuoteTemplateResource::collection($templates);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreQuoteTemplateRequest $request): JsonResponse
    {
        $this->authorize('create', QuoteTemplate::class);

        $template = QuoteTemplate::create([
            ...$request->validated(),
            'tenant_id' => $request->user()->tenant_id,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Quote template created successfully',
            'data' => new QuoteTemplateResource($template->load('creator'))
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(QuoteTemplate $quoteTemplate): QuoteTemplateResource
    {
        $this->authorize('view', $quoteTemplate);

        return new QuoteTemplateResource($quoteTemplate->load('creator'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateQuoteTemplateRequest $request, QuoteTemplate $quoteTemplate): JsonResponse
    {
        $this->authorize('update', $quoteTemplate);

        $quoteTemplate->update($request->validated());

        return response()->json([
            'message' => 'Quote template updated successfully',
            'data' => new QuoteTemplateResource($quoteTemplate->load('creator'))
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(QuoteTemplate $quoteTemplate): JsonResponse
    {
        $this->authorize('delete', $quoteTemplate);

        $quoteTemplate->delete();

        return response()->json([
            'message' => 'Quote template deleted successfully'
        ]);
    }

    /**
     * Generate a preview PDF for the template.
     */
    public function preview(QuoteTemplate $quoteTemplate)
    {
        $this->authorize('view', $quoteTemplate);

        // Create sample quote data for preview
        $sampleQuote = (object) [
            'quote_number' => 'Q-2025-SAMPLE',
            'deal' => (object) [
                'title' => 'Sample Deal for Preview',
                'company' => (object) [
                    'name' => 'Acme Corporation',
                    'address' => '123 Business St, Suite 100',
                    'city' => 'New York',
                    'state' => 'NY',
                    'zip' => '10001',
                    'email' => 'contact@acme.com'
                ],
                'contact' => (object) [
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'email' => 'john.doe@acme.com',
                    'phone' => '+1 (555) 123-4567'
                ]
            ],
            'items' => collect([
                (object) [
                    'name' => 'Web Development Service',
                    'description' => 'Custom web application development with modern technologies',
                    'quantity' => 1,
                    'unit_price' => 5000,
                    'discount' => 0,
                    'tax_rate' => 10,
                    'total' => 5500
                ],
                (object) [
                    'name' => 'Design Consultation',
                    'description' => 'UI/UX design consultation and wireframing',
                    'quantity' => 2,
                    'unit_price' => 500,
                    'discount' => 0,
                    'tax_rate' => 10,
                    'total' => 1100
                ]
            ]),
            'subtotal' => 6000,
            'discount' => 0,
            'tax' => 600,
            'total' => 6600,
            'currency' => 'USD',
            'status' => 'sent',
            'valid_until' => \Carbon\Carbon::parse('2025-12-31'),
            'template' => $quoteTemplate,
            'created_at' => \Carbon\Carbon::now(),
            'updated_at' => \Carbon\Carbon::now()
        ];

        try {
            // Generate PDF using the template's layout
            if (class_exists('Barryvdh\DomPDF\Facade\Pdf')) {
                // Use 'quote' variable name to match the Blade templates
                $quote = $sampleQuote;
                
                // Try normal render() first (works fine when storage is writable - LOCAL)
                // Only use in-memory compilation when permission errors occur (SERVER)
                try {
                    $html = view("quotes.pdf.{$quoteTemplate->layout}", compact('quote'))->render();
                    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
                    $pdf->setPaper('A4', 'portrait');
                    $pdf->setOptions(['isRemoteEnabled' => true]);
                } catch (\Exception $e) {
                    $errorMessage = $e->getMessage();
                    
                    // Check if error is related to storage permissions
                    $isPermissionError = strpos($errorMessage, 'Permission denied') !== false || 
                        strpos($errorMessage, 'Failed to open stream') !== false ||
                        strpos($errorMessage, 'storage/framework/views') !== false ||
                        strpos($errorMessage, 'file_put_contents') !== false;
                    
                    if ($isPermissionError) {
                        // Permission error - try in-memory compilation (bypasses storage)
                        try {
                            $html = $this->compileBladeInMemory("quotes.pdf.{$quoteTemplate->layout}", compact('quote'));
                            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
                            $pdf->setPaper('A4', 'portrait');
                            $pdf->setOptions(['isRemoteEnabled' => true]);
                            
                            \Illuminate\Support\Facades\Log::info('PDF preview: Used in-memory compilation due to permission error', [
                                'template' => "quotes.pdf.{$quoteTemplate->layout}",
                                'template_id' => $quoteTemplate->id
                            ]);
                        } catch (\Exception $e2) {
                            // In-memory also failed, try temp directory
                            try {
                                $tempViewPath = sys_get_temp_dir() . '/laravel_views_' . md5(base_path());
                                if (!is_dir($tempViewPath)) {
                                    @mkdir($tempViewPath, 0755, true);
                                }
                                
                                if (is_dir($tempViewPath) && is_writable($tempViewPath)) {
                                    $originalPath = config('view.compiled');
                                    config(['view.compiled' => $tempViewPath]);
                                    
                                    try {
                                        \Illuminate\Support\Facades\Artisan::call('view:clear');
                                    } catch (\Exception $clearException) {
                                        // Ignore
                                    }
                                    
                                    $view = app('view');
                                    $compiler = $view->getEngineResolver()->resolve('blade')->getCompiler();
                                    if (method_exists($compiler, 'setCompiledPath')) {
                                        $compiler->setCompiledPath($tempViewPath);
                                    }
                                    
                                    $html = view("quotes.pdf.{$quoteTemplate->layout}", compact('quote'))->render();
                                    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
                                    $pdf->setPaper('A4', 'portrait');
                                    $pdf->setOptions(['isRemoteEnabled' => true]);
                                    
                                    config(['view.compiled' => $originalPath]);
                                    
                                    \Illuminate\Support\Facades\Log::info('PDF preview: Used temp directory', [
                                        'template' => "quotes.pdf.{$quoteTemplate->layout}",
                                        'template_id' => $quoteTemplate->id
                                    ]);
                                } else {
                                    throw $e2;
                                }
                            } catch (\Exception $e3) {
                                // Last resort: use loadView
                                \Illuminate\Support\Facades\Log::warning('PDF preview: All methods failed, using loadView', [
                                    'error' => $e3->getMessage(),
                                    'template' => "quotes.pdf.{$quoteTemplate->layout}",
                                    'template_id' => $quoteTemplate->id
                                ]);
                                $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView("quotes.pdf.{$quoteTemplate->layout}", compact('quote'));
                            }
                        }
                    } else {
                        // Not a permission error, use loadView
                        \Illuminate\Support\Facades\Log::warning('PDF preview: render() failed, using loadView', [
                            'error' => $errorMessage,
                            'template' => "quotes.pdf.{$quoteTemplate->layout}",
                            'template_id' => $quoteTemplate->id
                        ]);
                        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView("quotes.pdf.{$quoteTemplate->layout}", compact('quote'));
                    }
                }
                
                return response($pdf->output(), 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="template-preview.pdf"'
                ]);
            } else {
                // Fallback to text response if DomPDF not available
                return response()->json([
                    'message' => 'PDF generation not available. Template layout: ' . $quoteTemplate->layout,
                    'template' => $quoteTemplate->name,
                    'layout' => $quoteTemplate->layout
                ]);
            }
        } catch (\Exception $e) {
            // Check if error is related to file permissions
            $errorMessage = $e->getMessage();
            if (strpos($errorMessage, 'Permission denied') !== false || strpos($errorMessage, 'Failed to open stream') !== false) {
                return response()->json([
                    'error' => 'Storage permission issue',
                    'message' => 'Unable to write compiled views. Please ensure storage/framework/views is writable.',
                    'path' => storage_path('framework/views'),
                    'hint' => 'On Linux servers, run: chmod -R 775 storage && chown -R www-data:www-data storage'
                ], 500);
            }
            
            return response()->json([
                'error' => 'Failed to generate preview',
                'message' => $errorMessage
            ], 500);
        }
    }

    /**
     * Compile Blade template to HTML in memory without writing to storage
     * This completely bypasses storage permission issues
     * 
     * @param string $templateName The view name (e.g., 'quotes.pdf.classic')
     * @param array $data Data to pass to the view
     * @return string Compiled HTML
     * @throws \Exception If compilation fails
     */
    private function compileBladeInMemory(string $templateName, array $data): string
    {
        try {
            // Get the Blade compiler and view finder
            $view = app('view');
            $compiler = $view->getEngineResolver()->resolve('blade')->getCompiler();
            $viewFinder = $view->getFinder();
            
            // Find the Blade template file
            $path = $viewFinder->find($templateName);
            $bladeContent = file_get_contents($path);
            
            // Compile Blade to PHP code (in memory, no file write)
            $phpCode = $compiler->compileString($bladeContent);
            
            // Extract variables for the view
            extract($data);
            
            // Execute the compiled PHP code and capture output
            ob_start();
            eval('?>' . $phpCode);
            $html = ob_get_clean(); 
            
            return $html;
        } catch (\Exception $e) {
            // If in-memory compilation fails, throw to trigger fallback
            \Illuminate\Support\Facades\Log::warning('In-memory Blade compilation failed', [
                'template' => $templateName,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
