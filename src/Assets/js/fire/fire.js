/**
 * Generates initial particle data.
 * 
 * @param {number} num_parts - Number of particles to generate.
 * @param {number} min_age - Minimum age of particles.
 * @param {number} max_age - Maximum age of particles.
 * @returns {number[]} Initial particle data.
 */
function initialParticleData(num_parts, min_age, max_age) {
	var data = [];
	for (var i = 0; i < num_parts; ++i) {
		data.push(0.0); // Position X
		data.push(0.0); // Position Y
		var life = min_age + Math.random() * (max_age - min_age);
		data.push(life + 1); // Age (start aged to respawn immediately)
		data.push(life); // Life
		data.push(0.0); // Velocity X
		data.push(0.0); // Velocity Y
		// Add initial Size (will be randomized on first update in shader)
		data.push(1.0); // Initial Size (Placeholder)
		// Add initial Rotation (will be randomized on first update in shader)
		data.push(0.0); // Initial Rotation (Placeholder)
	}
	return data;
}

function createShader(gl, shader_info) {
	var shader = gl.createShader(shader_info.type);
	var i = 0;
	var shader_source = document.getElementById(shader_info.name).text;
	/* skip whitespace to avoid glsl compiler complaining about
	#version not being on the first line*/
	while (/\s/.test(shader_source[i])) i++;
	shader_source = shader_source.slice(i);
	gl.shaderSource(shader, shader_source);
	gl.compileShader(shader);
	var compile_status = gl.getShaderParameter(shader, gl.COMPILE_STATUS);
	if (!compile_status) {
		var error_message = gl.getShaderInfoLog(shader);
		throw "Could not compile shader \"" +
		shader_info.name +
		"\" \n" +
		error_message;
	}
	return shader;
}

/* Creates an OpenGL program object.
   `gl' shall be a WebGL 2 context.
   `shader_list' shall be a list of objects, each of which have a `name'
      and `type' properties. `name' will be used to locate the script tag
      from which to load the shader. `type' shall indicate shader type (i. e.
      gl.FRAGMENT_SHADER, gl.VERTEX_SHADER, etc.)
  `transform_feedback_varyings' shall be a list of varying that need to be
    captured into a transform feedback buffer.*/
function createGLProgram(gl, shader_list, transform_feedback_varyings) {
	var program = gl.createProgram();
	for (var i = 0; i < shader_list.length; i++) {
		var shader_info = shader_list[i];
		var shader = createShader(gl, shader_info);
		gl.attachShader(program, shader);
	}

	/* Specify varyings that we want to be captured in the transform
	   feedback buffer. */
	if (transform_feedback_varyings != null) {
		gl.transformFeedbackVaryings(program,
			transform_feedback_varyings,
			gl.INTERLEAVED_ATTRIBS);
	}
	gl.linkProgram(program);
	var link_status = gl.getProgramParameter(program, gl.LINK_STATUS);
	if (!link_status) {
		var error_message = gl.getProgramInfoLog(program);
		throw "Could not link program.\n" + error_message;
	}
	return program;
}

function randomRGData(size_x, size_y) {
	var d = [];
	for (var i = 0; i < size_x * size_y; ++i) {
		d.push(Math.random() * 255.0);
		d.push(Math.random() * 255.0);
	}
	return new Uint8Array(d);
}

function initialParticleData(num_parts, min_age, max_age) {
	var data = [];
	for (var i = 0; i < num_parts; ++i) {
		data.push(0.0);
		data.push(0.0);
		var life = min_age + Math.random() * (max_age - min_age);
		data.push(life + 1);
		data.push(life);
		data.push(0.0);
		data.push(0.0);
		// Add initial Size (will be randomized on first update in shader)
		data.push(1.0); // Initial Size (Placeholder)
		// Add initial Rotation (will be randomized on first update in shader)
		data.push(0.0); // Initial Rotation (Placeholder)
	}
	return data;
}

function setupParticleBufferVAO(gl, buffers, vao) {
	gl.bindVertexArray(vao);
	for (var i = 0; i < buffers.length; i++) {
		var buffer = buffers[i];
		gl.bindBuffer(gl.ARRAY_BUFFER, buffer.buffer_object);
		var offset = 0;
		for (var attrib_name in buffer.attribs) {
			if (buffer.attribs.hasOwnProperty(attrib_name)) {
				var attrib_desc = buffer.attribs[attrib_name];
				gl.enableVertexAttribArray(attrib_desc.location);
				gl.vertexAttribPointer(
					attrib_desc.location,
					attrib_desc.num_components,
					attrib_desc.type,
					false,
					buffer.stride,
					offset);
				var type_size = 4; /* we're only dealing with types of 4 byte size in this demo, unhardcode if necessary */
				offset += attrib_desc.num_components * type_size;
				if (attrib_desc.hasOwnProperty("divisor")) {
					gl.vertexAttribDivisor(attrib_desc.location, attrib_desc.divisor);
				}
			}
		}
	}
	gl.bindVertexArray(null);
	gl.bindBuffer(gl.ARRAY_BUFFER, null);
}

function init(
	gl,
	num_particles,
	particle_birth_rate,
	min_age,
	max_age,
	min_theta,
	max_theta,
	min_speed,
	max_speed,
	gravity,
	part_img) { // Note the new parameter.
	if (max_age < min_age) {
		throw "Invalid min-max age range.";
	}
	if (max_theta < min_theta ||
		min_theta < -Math.PI ||
		max_theta > Math.PI) {
		throw "Invalid theta range.";
	}
	if (min_speed > max_speed) {
		throw "Invalid min-max speed range.";
	}
	var update_program = createGLProgram(
		gl,
		[
			{name: "particle-update-vert", type: gl.VERTEX_SHADER},
			{name: "passthru-frag-shader", type: gl.FRAGMENT_SHADER},
		],
		[
			"v_Position",
			"v_Age",
			"v_Life",
			"v_Velocity",
			"v_Size",
			"v_Rotation",
		]);
	var render_program = createGLProgram(
		gl,
		[
			{name: "particle-render-vert", type: gl.VERTEX_SHADER},
			{name: "particle-render-frag", type: gl.FRAGMENT_SHADER},
		],
		null);
	var update_attrib_locations = {
		i_Position: {
			location: gl.getAttribLocation(update_program, "i_Position"),
			num_components: 2,
			type: gl.FLOAT
		},
		i_Age: {
			location: gl.getAttribLocation(update_program, "i_Age"),
			num_components: 1,
			type: gl.FLOAT
		},
		i_Life: {
			location: gl.getAttribLocation(update_program, "i_Life"),
			num_components: 1,
			type: gl.FLOAT
		},
		i_Velocity: {
			location: gl.getAttribLocation(update_program, "i_Velocity"),
			num_components: 2,
			type: gl.FLOAT
		},
		i_Size: {
			location: gl.getAttribLocation(update_program, "i_Size"),
			num_components: 1,
			type: gl.FLOAT
		},
		i_Rotation: {
			location: gl.getAttribLocation(update_program, "i_Rotation"),
			num_components: 1,
			type: gl.FLOAT
		}
	};
	var render_attrib_locations = {
		i_Position: {
			location: gl.getAttribLocation(render_program, "i_Position"),
			num_components: 2,
			type: gl.FLOAT,
			divisor: 1
		},
		i_Age: {
			location: gl.getAttribLocation(render_program, "i_Age"),
			num_components: 1,
			type: gl.FLOAT,
			divisor: 1
		},
		i_Life: {
			location: gl.getAttribLocation(render_program, "i_Life"),
			num_components: 1,
			type: gl.FLOAT,
			divisor: 1
		},
		i_Size: {
			location: gl.getAttribLocation(render_program, "i_Size"),
			num_components: 1,
			type: gl.FLOAT,
			divisor: 1
		},
		i_Rotation: {
			location: gl.getAttribLocation(render_program, "i_Rotation"),
			num_components: 1,
			type: gl.FLOAT,
			divisor: 1
		}
	};
	var vaos = [
		gl.createVertexArray(),
		gl.createVertexArray(),
		gl.createVertexArray(),
		gl.createVertexArray()
	];
	var buffers = [
		gl.createBuffer(),
		gl.createBuffer(),
	];
	var sprite_vert_data =
		new Float32Array([
			1, 1,
			1, 1,

			-1, 1,
			0, 1,

			-1, -1,
			0, 0,

			1, 1,
			1, 1,

			-1, -1,
			0, 0,

			1, -1,
			1, 0]);
	var sprite_attrib_locations = {
		i_Coord: {
			location: gl.getAttribLocation(render_program, "i_Coord"),
			num_components: 2,
			type: gl.FLOAT,
		},
		i_TexCoord: {
			location: gl.getAttribLocation(render_program, "i_TexCoord"),
			num_components: 2,
			type: gl.FLOAT
		}
	};
	var sprite_vert_buf = gl.createBuffer();
	gl.bindBuffer(gl.ARRAY_BUFFER, sprite_vert_buf);
	gl.bufferData(gl.ARRAY_BUFFER, sprite_vert_data, gl.STATIC_DRAW);
	var vao_desc = [
		{
			vao: vaos[0],
			buffers: [{
				buffer_object: buffers[0],
				stride: 4 * 8, // 32 bytes
				attribs: update_attrib_locations
			}]
		},
		{
			vao: vaos[1],
			buffers: [{
				buffer_object: buffers[1],
				stride: 4 * 8, // 32 bytes
				attribs: update_attrib_locations
			}]
		},
		{
			vao: vaos[2],
			buffers: [{
				buffer_object: buffers[0],
				stride: 4 * 8, // 32 bytes
				attribs: render_attrib_locations
			},
				{
					buffer_object: sprite_vert_buf,
					stride: 4 * 4,
					attribs: sprite_attrib_locations
				}],
		},
		{
			vao: vaos[3],
			buffers: [{
				buffer_object: buffers[1],
				stride: 4 * 8, // 32 bytes
				attribs: render_attrib_locations
			},
				{
					buffer_object: sprite_vert_buf,
					stride: 4 * 4,
					attribs: sprite_attrib_locations
				}],
		},
	];
	var initial_data =
		new Float32Array(initialParticleData(num_particles, min_age, max_age));
	gl.bindBuffer(gl.ARRAY_BUFFER, buffers[0]);
	gl.bufferData(gl.ARRAY_BUFFER, initial_data, gl.STREAM_DRAW);
	gl.bindBuffer(gl.ARRAY_BUFFER, buffers[1]);
	gl.bufferData(gl.ARRAY_BUFFER, initial_data, gl.STREAM_DRAW);
	for (var i = 0; i < vao_desc.length; i++) {
		setupParticleBufferVAO(gl, vao_desc[i].buffers, vao_desc[i].vao);
	}

	// Set clear color to transparent black
	gl.clearColor(0.0, 0.0, 0.0, 0.0);

	var rg_noise_texture = gl.createTexture();
	gl.bindTexture(gl.TEXTURE_2D, rg_noise_texture);
	gl.texImage2D(gl.TEXTURE_2D,
		0,
		gl.RG8,
		512, 512,
		0,
		gl.RG,
		gl.UNSIGNED_BYTE,
		randomRGData(512, 512));
	gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.MIRRORED_REPEAT);
	gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.MIRRORED_REPEAT);
	gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.NEAREST);
	gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.NEAREST);
	gl.enable(gl.BLEND);
	gl.blendFunc(gl.SRC_ALPHA, gl.ONE_MINUS_SRC_ALPHA);

	var particle_tex = gl.createTexture();
	gl.bindTexture(gl.TEXTURE_2D, particle_tex);
	// Use actual image dimensions instead of hardcoded 32x32
	gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA8, part_img.width, part_img.height, 0, gl.RGBA, gl.UNSIGNED_BYTE, part_img);
	gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.LINEAR);
	gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.LINEAR);
	return {
		particle_sys_buffers: buffers,
		particle_sys_vaos: vaos,
		read: 0,
		write: 1,
		particle_update_program: update_program,
		particle_render_program: render_program,
		num_particles: initial_data.length / 8,
		old_timestamp: 0.0,
		rg_noise: rg_noise_texture,
		total_time: 0.0,
		born_particles: 0,
		birth_rate: particle_birth_rate,
		gravity: gravity,
		origin: [0.0, 0.0],
		min_theta: min_theta,
		max_theta: max_theta,
		min_speed: min_speed,
		max_speed: max_speed,
		particle_tex: particle_tex
	};
}

function render(gl, state, timestamp_millis) {
	// Calculate particle count and time step
	var num_part = state.born_particles;
	var time_delta = state.old_timestamp === 0 ? 0.0 : timestamp_millis - state.old_timestamp;
	if (time_delta > 500.0) { // Prevent large jumps if tab was inactive
		time_delta = 0.0; 
	}
	state.old_timestamp = timestamp_millis; // Update timestamp

	// Spawn new particles gradually
	if (state.born_particles < state.num_particles) {
		state.born_particles = Math.min(state.num_particles,
			state.born_particles + state.birth_rate * time_delta / 1000.0);
	}

	// Setup transform feedback
	gl.useProgram(state.particle_update_program);
	gl.uniform1f(gl.getUniformLocation(state.particle_update_program, "u_TotalTime"), timestamp_millis / 1000.0);
	gl.uniform1f(gl.getUniformLocation(state.particle_update_program, "u_TimeDelta"), time_delta / 1000.0);
	// Note: u_MousePos, u_MouseVelocity, u_IsMouseMoving, u_MouseInfluence are now set directly by event listeners
	gl.bindVertexArray(state.particle_sys_vaos[state.read]);
	gl.bindBufferBase(gl.TRANSFORM_FEEDBACK_BUFFER, 0, state.particle_sys_buffers[state.write]);
	gl.beginTransformFeedback(gl.POINTS);
	gl.drawArrays(gl.POINTS, 0, num_part);
	gl.endTransformFeedback();
	gl.bindBufferBase(gl.TRANSFORM_FEEDBACK_BUFFER, 0, null);

	// --- Rendering Pass ---
	gl.bindFramebuffer(gl.FRAMEBUFFER, null); // Render to canvas
	gl.viewport(0, 0, gl.canvas.width, gl.canvas.height);
	gl.clear(gl.COLOR_BUFFER_BIT);

	// Setup rendering pass
	gl.useProgram(state.particle_render_program);
	gl.bindVertexArray(state.particle_sys_vaos[state.read + 2]);

	// Blend mode for particles
	gl.enable(gl.BLEND);
	gl.blendFunc(gl.SRC_ALPHA, gl.ONE_MINUS_SRC_ALPHA); // Standard alpha blending

	// Set render uniforms
	var aspect = state.canvas_width / state.canvas_height;
	gl.uniform1i(gl.getUniformLocation(state.particle_render_program, "u_Sprite"), 0); // Texture unit 0
	gl.uniform1f(gl.getUniformLocation(state.particle_render_program, "u_Aspect"), aspect);

	// Draw particles
	gl.drawArraysInstanced(gl.TRIANGLES, 0, 6, num_part); // 6 vertices per quad

	gl.disable(gl.BLEND);

	// Swap buffers
	var tmp = state.read;
	state.read = state.write;
	state.write = tmp;

	// Request next frame
	window.requestAnimationFrame(function (ts) {
		render(gl, state, ts);
	});
}

function setupEventListeners(gl, canvas_element, state) {
	// Variables for mouse velocity calculation
	let lastMouseX = -1, lastMouseY = -1;
	let lastMouseTime = 0;
	let mouseVelocityX = 0, mouseVelocityY = 0;
	let mouseMoveTimeout;

	// Get uniform locations once
	const uMousePosLoc = gl.getUniformLocation(state.particle_update_program, "u_MousePos");
	const uMouseVelLoc = gl.getUniformLocation(state.particle_update_program, "u_MouseVelocity");
	const uIsMovingLoc = gl.getUniformLocation(state.particle_update_program, "u_IsMouseMoving");
	const uMouseInfLoc = gl.getUniformLocation(state.particle_update_program, "u_MouseInfluence");

	canvas_element.addEventListener('mousemove', (e) => {
		const rect = canvas_element.getBoundingClientRect();
		const canvasWidth = rect.width || 1;
		const canvasHeight = rect.height || 1;
		const mouseX = ((e.clientX - rect.left) / canvasWidth) * 2 - 1;
		const mouseY = ((rect.bottom - e.clientY) / canvasHeight) * 2 - 1; // Flipped Y

		// Update position uniform
		gl.uniform2f(uMousePosLoc, mouseX, mouseY);

		const now = Date.now();
		const dt = (now - lastMouseTime) / 1000.0; // Time delta in seconds

		if (lastMouseX !== -1 && dt > 0.001) { // Avoid division by zero/tiny dt
			const dx = mouseX - lastMouseX;
			const dy = mouseY - lastMouseY;

			mouseVelocityX = dx / dt;
			mouseVelocityY = dy / dt;

			const len = Math.sqrt(mouseVelocityX * mouseVelocityX + mouseVelocityY * mouseVelocityY);
			const speedThreshold = 0.05; 

			if (len > speedThreshold) {
				// Update velocity and moving state uniforms
				gl.uniform2f(uMouseVelLoc, mouseVelocityX, mouseVelocityY);
				gl.uniform1f(uIsMovingLoc, 1.0);
				
				clearTimeout(mouseMoveTimeout);
				mouseMoveTimeout = setTimeout(() => {
					gl.uniform2f(uMouseVelLoc, 0.0, 0.0);
					gl.uniform1f(uIsMovingLoc, 0.0);
				}, 100); // Reset after 100ms
			} 
		}

		lastMouseX = mouseX;
			lastMouseY = mouseY;
		lastMouseTime = now;
	});

	canvas_element.addEventListener('mouseenter', () => {
		gl.uniform1f(uMouseInfLoc, 0.5); // Turn on influence
		// Optional: Reset last mouse position to current to avoid jump in velocity?
		// const rect = canvas_element.getBoundingClientRect();
		// lastMouseX = ((event.clientX - rect.left) / rect.width) * 2 - 1;
		// lastMouseY = ((rect.bottom - event.clientY) / rect.height) * 2 - 1;
		// lastMouseTime = Date.now();
	});

	canvas_element.addEventListener('mouseleave', () => {
		gl.uniform1f(uMouseInfLoc, 0.0); // Turn off influence
		gl.uniform2f(uMousePosLoc, -2.0, -2.0); // Move emitter off-screen
		gl.uniform2f(uMouseVelLoc, 0.0, 0.0); // Reset velocity
		gl.uniform1f(uIsMovingLoc, 0.0); // Reset moving state
		clearTimeout(mouseMoveTimeout); // Clear any pending reset
		lastMouseX = -1; // Reset position tracking
		lastMouseY = -1;
	});
}

// Main initialization function
function main() {
	const canvas_element = document.getElementById('fire-canvas');
	if (!canvas_element) {
		console.error("Could not find canvas element with id 'fire-canvas'");
		return;
	}

	const webgl_context = getWebGLContext(canvas_element);
	if (!webgl_context) return;

	const part_img = new Image();
	part_img.src = canvas_element.getAttribute('data-particle-src');

	part_img.onload = () => {
		try {
			const state = init(
				webgl_context,
				30, // Particle count
				80, // Birth rate
				0.4, 0.8, // Lifetime range
				-Math.PI, Math.PI, // Angle range
				0.03, 0.6, // Speed range
				[0.0, -0.1], // Gravity
				part_img // Texture
			);

			// Set up the consolidated event listeners
			setupEventListeners(webgl_context, canvas_element, state);

			// Start the animation loop
			window.requestAnimationFrame((ts) => {
				render(webgl_context, state, ts);
			});
		} catch (e) {
			console.error('Error initializing fire effect:', e);
		}
	};

	part_img.onerror = () => {
		console.error("Failed to load particle image: " + part_img.src);
	};
}

// Run main when the document is ready
if (document.readyState === 'loading') { // Loading hasn't finished yet
	document.addEventListener('DOMContentLoaded', main);
} else { // `DOMContentLoaded` has already fired
	main();
}
