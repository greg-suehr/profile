import React from 'react';
import Tilt from 'react-tilt';
import logo from './logo.png'

const Logo = () => {
	return (
		<div className='ma3'>
		<Tilt className="Tilt" options={{ max:25 }}>
		  <div className="Tilt-inner"> 
		  	<img src={logo} alt="A very cool logo!" height="100px"/>
		  </div>
		</Tilt>
		</div>
	);
}

export default Logo;